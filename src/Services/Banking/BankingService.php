<?php

namespace Larapress\ECommerce\Services\Banking;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Larapress\CRUD\BaseFlags;
use Larapress\CRUD\Events\CRUDCreated;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\CRUD\GiftCodeCRUDProvider;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\BankGateway;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\ECommerce\Models\GiftCodeUse;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\Events\BankGatewayTransactionEvent;
use Larapress\ECommerce\Services\Banking\Events\CartPurchasedEvent;
use Larapress\ECommerce\Services\Banking\Events\WalletTransactionEvent;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\FormEntry;

class BankingService implements IBankingService
{
    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $gateway_id
     * @param float $amount
     * @param int $currency
     * @param callback|null $onFailed
     * @return Response
     */
    public function redirectToBankForAmount(Request $request, $gateway_id, $amount, $currency, $onFailed, $onAlreadyPurchased)
    {
        /** @var IECommerceUser */
        $user = Auth::user();
        /** @var Cart */
        $cart = Cart::updateOrCreate([
            'currency' => $currency,
            'customer_id' => $user->id,
            'domain_id' => $user->getMembershipDomainId(),
            'flags' => Cart::FLAGS_INCREASE_WALLET,
            'status' => Cart::STATUS_UNVERIFIED,
        ], [
            'amount' => $amount,
            'data' => []
        ]);
        CRUDUpdated::dispatch($user, $cart, CartCRUDProvider::class, Carbon::now());

        return $this->redirectToBankForCart($request, $cart, $gateway_id, $onFailed, $onAlreadyPurchased);
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param array $ids
     * @param int $currency
     * @return Cart
     */
    public function createCartWithProductIDs(Request $request, IECommerceUser $user, array $ids, $currency)
    {
        $cart = Cart::create([
            'customer_id' => $user->id,
            'domain_id' => $user->getMembershipDomainId(),
            'amount' => 0,
            'currency' => $currency,
            'flags' => Cart::FLAGS_SYSTEM_API,
            'status' => Cart::STATUS_UNVERIFIED,
        ]);

        $products = Product::whereIn('id', $ids)->get()->keyBy('id');
        $amount = 0;
        foreach ($ids as $id) {
            $amount += $products[$id]->price();
            $cart->products()->attach($id, [
                'amount' => $products[$id]->price(),
                'currency' => $currency,
            ]);
        }

        $cart->update([
            'amount' => $amount,
            'data' => [
                'kanoon_api_at' => Carbon::now(),
            ]
        ]);

        return $cart;
    }

    /**
     * @param Request            $request
     * @param Cart|int           $cart
     * @param BankGateway|int    $gateway_id
     * @param callable           $onFailed
     *
     * @return Response
     */
    public function redirectToBankForCart(Request $request, $cart, $gateway_id, $onFailed, $onAlreadyPurchased)
    {
        if (is_numeric($cart)) {
            /** @var Cart $cart */
            $cart = Cart::with(['products'])->find($cart);
        }

        /** @var IECommerceUser */
        $user = Auth::user();
        if ($user->id !== $cart->customer_id) {
            throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
        }

        if ($cart->isPaid()) {
            return $onAlreadyPurchased($request, $cart);
        }

        $domain = $user->getMembershipDomain();
        $balance = $this->getUserBalance($user, $cart->currency);

        if ((isset($cart->data['use_balance']) && $cart->data['use_balance'] && floatval($balance['amount']) >= floatval($cart->amount)) || $cart->amount === 0) {
            try {
                DB::beginTransaction();
                $this->markCartPurchased($request, $cart);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::critical('Bank Gateway failed redirect', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'stack' => $e->getTraceAsString(),
                ]);
                return $onFailed($request, $cart, $e);
            }


            $this->resetPurchasedCache($cart->customer_id);
            return $onAlreadyPurchased($request, $cart);
        }

        $avPorts = config('larapress.ecommerce.banking.ports');
        $gatewayData = BankGateway::find($gateway_id);

        if (is_null($gatewayData)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        /** @var IBankPortInterface */
        $port = new $avPorts[$gatewayData->type]($gatewayData);

        [$portPrice, $portCurrency] = $port->convertForPriceAndCurrency(floatval($cart->amount), $cart->currency);
        if (isset($cart->data['use_balance']) && $cart->data['use_balance'] && floatval($balance['amount']) < $cart->amount) {
            $portPrice = $portPrice - floatval($balance['amount']);
        }

        $return_to = $request->get('return_to', null);
        $cartData = $cart->data;
        $cartData['return_to'] = $return_to;
        $cart->update([
            'data' => $cartData,
        ]);

        /** @var BankGatewayTransaction */
        $transaction = BankGatewayTransaction::create([
            'amount' => $portPrice,
            'currency' => $portCurrency,
            'cart_id' => $cart->id,
            'customer_id' => $user->id,
            'bank_gateway_id' => $gatewayData->id,
            'domain_id' => $domain->id,
            'status' => BankGatewayTransaction::STATUS_CREATED,
            'data' => [
                'description' => 'درخواست خرید سبد با شماره ' . $cart->id,
            ]
        ]);
        $callback = route(config('larapress.ecommerce.routes.bank_gateways.name') . '.any.callback', [
            'tr_id' => $transaction->id,
        ]);
        // reference keeping for redirect
        /// no logic here; just keep objects in memory update and ready
        $transaction->bank_gateway = $gatewayData;
        $transaction->domain = $domain;
        $transaction->customer = $user;

        BankGatewayTransactionEvent::dispatch($domain, $request->ip(), time(), $transaction);
        CRUDCreated::dispatch($user, $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

        return $port->redirect($request, $transaction, $callback);
    }

    /**
     * @param Request  $request
     * @param BankGatewayTransaction|int $transaction
     * @param callable $onAlreadyPurchased
     * @param callable $onSuccess
     * @param callable $onFailed
     *
     * @return Response
     */
    public function verifyBankRequest(Request $request, $transaction, $onAlreadyPurchased, $onSuccess, $onFailed, $onCancel)
    {
        if (is_numeric($transaction)) {
            /** @var Cart $cart */
            $transaction = BankGatewayTransaction::with([
                'cart',
                'customer',
                'bank_gateway',
                'domain'
            ])->find($transaction);
        }

        /** @var Cart */
        $cart = $transaction->cart;

        if ($cart->isPaid()) {
            return $onAlreadyPurchased($request, $cart, $transaction);
        }

        try {
            $avPorts = config('larapress.ecommerce.banking.ports');
            DB::beginTransaction();
            $gatewayData = $transaction->bank_gateway;
            /** @var IBankPortInterface */
            $port = new $avPorts[$gatewayData->type]($gatewayData);
            $transaction = $port->verify($request, $transaction);
            if ($transaction->status === BankGatewayTransaction::STATUS_SUCCESS) {
                $supportProfileId = isset($cart->customer->supportProfile['id']) ? $cart->customer->supportProfile['id'] : null;
                $wallet = WalletTransaction::create([
                    'user_id' => $cart->customer_id,
                    'domain_id' => $cart->domain_id,
                    // increase wallet amount = transaction amount, becouse
                    // we want to increase user wallet with the amount he just paid
                    // cart amount may differ to this value
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'type' => WalletTransaction::TYPE_REAL_MONEY,
                    'data' => [
                        'cart_id' => $cart->id,
                        'transaction_id' => $transaction->id,
                        'description' => trans('larapress::ecommerce.banking.messages.wallet-descriptions.cart_increased', ['cart_id' => $cart->id]),
                        'balance' => $this->getUserBalance($cart->customer, $cart->currency),
                        'support' => $supportProfileId,
                    ]
                ]);
                $this->markCartPurchased($request, $cart);
                DB::commit();

                $this->resetPurchasedCache($cart->customer_id);
                WalletTransactionEvent::dispatch($wallet, time());
                BankGatewayTransactionEvent::dispatch($cart->domain, $request->ip(), time(), $transaction);
                CRUDUpdated::dispatch(Auth::user(), $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());
                $this->resetBalanceCache($cart->customer_id);

                return $onSuccess($request, $cart, $transaction);
            } else {
                $transaction->update([
                    'status' => BankGatewayTransaction::STATUS_CANCELED,
                ]);
                DB::commit();

                BankGatewayTransactionEvent::dispatch($transaction->domain, $request->ip(), time(), $transaction);
                CRUDUpdated::dispatch(Auth::user(), $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

                return $onCancel($request, $cart, 'Bank Request Canceled');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::critical('Bank Gateway failed verify', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'stack' => $e->getTraceAsString(),
                'user_id' => $cart->customer_id,
                'cart_id' => $cart->id,
            ]);

            $data = $transaction->data;
            $data['exception_message'] = $e->getMessage();
            $transaction->update([
                'status' => BankGatewayTransaction::STATUS_FAILED,
                'data' => $data,
            ]);
            BankGatewayTransactionEvent::dispatch($transaction->domain, $request->ip(), time(), $transaction);
            CRUDUpdated::dispatch(Auth::user(), $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

            return $onFailed($request, $cart, $e->getMessage());
        }
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $cart_id
     * @return Response
     */
    public function updatePurchasingCart(Request $request, IECommerceUser $user, int $currency, $cart_id = null)
    {
        $cart = is_null($cart_id) ? $this->getPurchasingCart($user, $currency) : Cart::find($cart_id);
        if (is_null($cart) || $cart->customer_id !== $user->id) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        // calls for CRUDUpdate on cart inside and resets cache
        $this->updateCartWithRequest($user, $cart, $request);
        $balance = $this->getUserBalance($user, $currency);
        return [
            'cart' => [
                'amount' => !$cart->data['use_balance'] ?  $cart->amount : max(0, floatval($cart->amount) - floatval($balance['amount'])),
            ],
            'balance' => $balance,
        ];
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param ICartItem $cartItem
     * @return Cart
     */
    public function addItemToPurchasingCart(Request $request, IECommerceUser $user, ICartItem $cartItem)
    {
        /** @var Cart */
        $cart = $this->getPurchasingCart($user, $cartItem->currency());

        $existingItems = $this->getPurchasingCartItems($user, $cartItem->currency());
        $existingItemsIds = [];
        foreach ($existingItems as $eItem) {
            $existingItemsIds[] = $eItem->id;
            if ($eItem->id === $cartItem->id) {
                // already exists;
                // @todo: add to quantity if needed!
                return $cart;
            }
        }

        try {
            DB::beginTransaction();
            // remove item children if already in the cart
            if ($cartItem->children) {
                $childIds = $cartItem->children->pluck('id');
                $cart->products()->detach($childIds);
            }

            // check if items parent is already in cart
            if ($cartItem->parent) {
                $ancestors = $this->getProductAncestors($cartItem);
                foreach ($ancestors as $ans) {
                    if (in_array($ans->id, $existingItemsIds)) {
                        // parent object already in cart, can not add this item
                        throw new AppException(AppException::ERR_INVALID_QUERY);
                    }
                }
            }

            $cart->products()->attach($cartItem->model(), [
                'amount' => $cartItem->price(),
                'currency' => $cartItem->currency(),
            ]);
            $cart->update([
                'amount' => $cart->amount + $cartItem->price()
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
        $this->resetPurchasingCache($user->id);
        $cart['items'] = $cart->products()->get();
        CRUDUpdated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param ICartItem $cartItem
     * @return Cart
     */
    public function removeItemFromPurchasingCart(Request $request, IECommerceUser $user, ICartItem $cartItem)
    {
        /** @var Cart */
        $cart = $this->getPurchasingCart($user, $cartItem->currency());

        $cart->products()->detach($cartItem->model());

        $periodicIds = isset($cart->data['periodic_product_ids']) ? $cart->data['periodic_product_ids'] : [];
        if (in_array($cartItem->id, $periodicIds)) {
            $cart->update([
                'amount' => $cart->amount - $cartItem->pricePeriodic()
            ]);
        } else {
            $cart->update([
                'amount' => $cart->amount - $cartItem->price()
            ]);
        }

        $this->resetPurchasingCache($user->id);
        $cart['items'] = $cart->products()->get();
        CRUDUpdated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param integer $currency
     * @return Cart
     */
    public function getPurchasingCart(IECommerceUser $user, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchase-cart',
            function () use ($user, $currency) {
                if (is_null($user->getMembershipDomainId())) {
                    throw new AppException(AppException::ERR_USER_HAS_NO_DOMAIN);
                }

                $cart = Cart::query()
                    ->where('customer_id', $user->id)
                    ->where('domain_id', $user->getMembershipDomainId())
                    ->where('currency', $currency)
                    ->where('flags', '&', Cart::FLAGS_USER_CART)
                    ->where('status', '=', Cart::STATUS_UNVERIFIED)
                    ->first();

                if (is_null($cart)) {
                    $cart = Cart::create([
                        'amount' => 0,
                        'currency' => $currency,
                        'customer_id' => $user->id,
                        'domain_id' => $user->getMembershipDomainId(),
                        'flags' => Cart::FLAGS_USER_CART,
                        'status' => Cart::STATUS_UNVERIFIED,
                        'data' => []
                    ]);
                }
                return $cart;
            },
            ['purchasing-cart:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param integer $currency
     * @return ICartItem[]
     */
    public function getPurchasingCartItems(IECommerceUser $user, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchase-cart-items',
            function () use ($user, $currency) {
                $cart = $this->getPurchasingCart($user, $currency);
                /** ICartItem[] */
                $items = $cart->products;
                $cacheItems = [];
                foreach ($items as $item) {
                    $cacheItems[] = $item->model();
                }
                return $cacheItems;
            },
            ['purchasing-cart:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return array
     */
    public function getPurchasedCarts(IECommerceUser $user)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchased-cart',
            function () use ($user) {
                return  Cart::query()
                    ->with(['products'])
                    ->where('customer_id', $user->id)
                    ->where('domain_id', $user->getMembershipDomainId())
                    ->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED])
                    ->get();
            },
            ['purchased-cart:' . $user->id],
            null,
        );
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return array
     */
    public function getPurchasedItemIds(IECommerceUser $user)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchased-cart-items',
            function () use ($user) {
                $carts = $this->getPurchasedCarts($user);
                $ids = [];
                $groups = [];
                foreach ($carts as $cart) {
                    foreach ($cart->products as $item) {
                        $ids[] = $item['id'];
                        if (!is_null($item['group']) && !empty($item['group'])) {
                            $groups[] = $item['group'];
                        }
                    }
                }


                if (
                    !is_null(config('larapress.ecommerce.lms.teacher_support_form_id')) &&
                    $user->hasRole(config('larapress.ecommerce.lms.owner_role_id'))
                ) {
                    $ids = array_merge($ids, $user->getOwenedProductsIds());
                }

                if (count($groups) > 0) {
                    // include product ids with same group
                    $groupedIds = Product::select('id')->whereIn('group', $groups)->get()->pluck('id')->toArray();
                    $ids = array_merge($ids, $groupedIds);
                }

                return $ids;
            },
            ['purchased-cart:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return array
     */
    public function getPeriodicInstallmentsLockedProducts(IECommerceUser $user)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.periodic-products',
            function () use ($user) {
                $carts = $this->getPurchasedCarts($user);
                $ids = [];
                $groups = [];
                $now = Carbon::now();
                foreach ($carts as $cart) {
                    if (BaseFlags::isActive($cart->flags, Cart::FLAGS_HAS_PERIODS) && !BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIODIC_COMPLETED)) {
                        if (isset($cart->data['periodic_custom'])) {
                            $periodConfig = array_map(function ($data) {
                                $data['payment_at'] = Carbon::parse($data['payment_at']);
                                return $data;
                            }, array_filter($cart->data['periodic_custom'], function($data) {
                                return isset($data['payment_at']) && !is_null($data['payment_at']);
                            }));
                            usort($periodConfig, function($a, $b) {
                                return $a['payment_at']->diffInDays($b['payment_at']);
                            });
                            $paymentInfo = null;
                            foreach ($periodConfig as $custom) {
                                if (isset($custom['status']) && $custom['status'] == 2) {
                                    $paymentInfo = $custom;
                                    break;
                                }
                            }

                            if (!is_null($paymentInfo) && isset($paymentInfo['payment_at'])) {
                                $payment_at = Carbon::parse($paymentInfo['payment_at']);
                                if ($now > $payment_at) {
                                    foreach ($cart->products as $product) {
                                        $ids[] = $product->id;
                                        if (!is_null($product->group) && !empty($product->group)) {
                                            $groups[] = $product->group;
                                        }
                                    }
                                }
                            }
                        } else if (isset($cart->data['periodic_product_ids']) && isset($cart->data['period_start'])) {
                            $periodicProducts = $cart->data['periodic_product_ids'];
                            foreach ($cart->products as $product) {
                                if (in_array($product->id, $periodicProducts)) {
                                    $period_start = Carbon::parse($cart->data['period_start']);
                                    $alreadyPaidPeriods = isset($cart->data['periodic_payments']) ? $cart->data['periodic_payments'] : [];
                                    $alreadyPaidCount = isset($alreadyPaidPeriods[$product->id]) ? count($alreadyPaidPeriods[$product->id]) : 0;
                                    if (isset($product->data['calucalte_periodic'])) {
                                        $calc = $product->data['calucalte_periodic'];
                                        $duration = isset($calc['period_duration']) ? intval($calc['period_duration']) : 30;
                                        $total = isset($calc['period_count']) ? intval($calc['period_count']) : 1;
                                        if (isset($calc['ends_at']) && !is_null($calc['ends_at'])) {
                                            $endAt = Carbon::parse($calc['ends_at']);
                                            $remaingDays = $period_start->diffInDays($endAt);
                                            if ($remaingDays < $duration * $total) {
                                                $duration = floor($remaingDays / $total);
                                            }
                                        }

                                        $period_start->addDays($duration * ($alreadyPaidCount + 1) - 1);
                                        if ($now > $period_start) {
                                            $ids[] = $product->id;
                                            if (!is_null($product->group) && !empty($product->group)) {
                                                $groups[] = $product->group;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if (count($groups) > 0) {
                    // include product ids with same group
                    $groupedIds = Product::select('id')->whereIn('group', $groups)->get()->pluck('id')->toArray();
                    $ids = array_merge($ids, $groupedIds);
                }

                return $ids;
            },
            ['purchased-cart:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param float $amount
     * @param integer $currency
     * @param integer $type
     * @param integer $flags
     * @param string $desc
     * @return WalletTransaction
     */
    public function addBalanceForUser(IECommerceUser $user, float $amount, int $currency, int $type, int $flags, string $desc)
    {
        $supportProfileId = isset($user->supportProfile['id']) ? $user->supportProfile['id'] : null;
        $wallet = WalletTransaction::create([
            'user_id' => $user->id,
            'domain_id' => $user->getMembershipDomainId(),
            'amount' => $amount,
            'currency' => $currency,
            'type' => $type,
            'flags' => $flags,
            'data' => [
                'description' => $desc,
                'balance' => $this->getUserBalance($user, $currency),
                'support' => $supportProfileId,
            ]
        ]);

        $this->resetBalanceCache($user->id);
        WalletTransactionEvent::dispatch($wallet, time());

        return $wallet;
    }

    /**
     * Undocumented function
     *
     * @param int|Cart $originalCart
     * @param int|Product $product
     * @return Cart
     */
    public function getInstallmentsForProductInCart(IECommerceUser $user, $originalCart, $product)
    {
        if (is_numeric($originalCart)) {
            $originalCart = Cart::find($originalCart);
        }

        if (is_null($originalCart) || $originalCart->customer_id != $user->id) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        if (is_numeric($product)) {
            $product = Product::find($product);
        }

        if (is_null($product)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        $periodicIds = isset($originalCart->data['periodic_product_ids']) ? $originalCart->data['periodic_product_ids'] : [];
        $periodicIds = array_map(function ($item) {
            if (is_array($item) && isset($item['id'])) {
                return $item['id'];
            }
            return $item;
        }, $periodicIds);
        // product is not purchased periodic
        if (!in_array($product->id, $periodicIds)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        // product does not have periodic purchase info
        if (!isset($product->data['calucalte_periodic']) || !isset($product->data['calucalte_periodic']['period_count'])) {
            return null;
            // throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }

        $alreadyPaidPeriods = isset($originalCart->data['periodic_payments']) ? $originalCart->data['periodic_payments'] : [];
        $calc = $product->data['calucalte_periodic'];
        $count = $calc['period_count'];
        $alreadyPaidCount = isset($alreadyPaidPeriods[$product->id]) ? count($alreadyPaidPeriods[$product->id]) : 0;
        if ($alreadyPaidCount >= $count) {
            return null;
        }

        $period_start = Carbon::parse($originalCart->data['period_start']);
        $duration = isset($calc['period_duration']) ? intval($calc['period_duration']) : 30;
        $total = isset($calc['period_count']) ? intval($calc['period_count']) : 1;
        if (isset($calc['ends_at']) && !is_null($calc['ends_at'])) {
            $endAt = Carbon::parse($calc['ends_at']);
            $remaingDays = $period_start->diffInDays($endAt);
            if ($remaingDays < $duration * $total) {
                $duration = floor($remaingDays / $total);
            }
        }
        $due_date = $period_start->addDays($duration * ($alreadyPaidCount + 1));

        $amount = $calc['period_amount'];
        if (isset($originalCart->data['gift_code']['percent'])) {
            $gifted_products = isset($originalCart->data['gift_code']['products']) ? $originalCart->data['gift_code']['products'] : [];
            if (in_array($product->id, $gifted_products) || count($gifted_products) === 0) {
                $percent = floatval($originalCart->data['gift_code']['percent']);
                $amount = ceil((1 - $percent) * $amount);
            }
        }

        /** @var Cart */
        $cart = Cart::updateOrCreate([
            'currency' => $originalCart->currency,
            'customer_id' => $user->id,
            'domain_id' => $originalCart->domain_id,
            'flags' => Cart::FLAGS_PERIOD_PAYMENT_CART,
            'status' => Cart::STATUS_UNVERIFIED,
            'data->periodic_pay->product->id' => $product->id,
        ], [
            'amount' => $amount,
            'data' => [
                'periodic_pay' => [
                    'index' => $alreadyPaidCount + 1,
                    'total' => $count,
                    'product' => [
                        'id' => $product->id,
                        'title' => $product->data['title'],
                    ],
                    'originalCart' => $originalCart->id,
                    'due_date' => $due_date,
                ],
            ]
        ]);

        return $cart;
    }

    /**
     *
     */
    public function getInstallmentsForCartPeriodicCustom(IECommerceUser $user, $originalCart)
    {
        if (is_numeric($originalCart)) {
            $originalCart = Cart::find($originalCart);
        }

        if (is_null($originalCart) || $originalCart->customer_id != $user->id) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        // cart does not have periodic purchase custom info
        if (!isset($originalCart->data['periodic_custom']) || count($originalCart->data['periodic_custom']) === 0) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }

        $periodConfig = array_map(function ($data) {
            $data['payment_at'] = Carbon::parse($data['payment_at']);
            return $data;
        }, array_filter($originalCart->data['periodic_custom'], function($data) {
            return isset($data['payment_at']) && !is_null($data['payment_at']);
        }));
        usort($periodConfig, function($a, $b) {
            return $a['payment_at']->getTimestamp() - $b['payment_at']->getTimestamp();
        });
        $payment_index = -1;
        $paymentInfo = null;
        $totalPeriods = count($periodConfig);
        $indexer = 0;
        foreach ($periodConfig as $custom) {
            if (isset($custom['status']) && $custom['status'] == 2) {
                $payment_index = $indexer;
                $paymentInfo = $custom;
                break;
            }
            $indexer++;
        }

        if ($payment_index >= 0 && !is_null($paymentInfo) && isset($paymentInfo['amount']) && isset($paymentInfo['payment_at'])) {
            /** @var Cart */
            $cart = Cart::updateOrCreate([
                'currency' => $originalCart->currency,
                'customer_id' => $user->id,
                'domain_id' => $originalCart->domain_id,
                'flags' => Cart::FLAGS_PERIOD_PAYMENT_CART,
                'status' => Cart::STATUS_UNVERIFIED,
                'data->periodic_pay->originalCart' => $originalCart->id,
            ], [
                'amount' => $paymentInfo['amount'],
                'data' => [
                    'periodic_pay' => [
                        'custom' => true,
                        'index' => $payment_index,
                        'total' => $totalPeriods,
                        'originalCart' => $originalCart->id,
                        'due_date' => $paymentInfo['payment_at'],
                        'product_titles' => $originalCart->products->map(function ($item) {
                            return $item->data['title'];
                        })
                    ],
                ]
            ]);
            return $cart;
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @return Cart[]
     */
    public function getInstallmentsForPeriodicPurchases()
    {
        Cart::query()
            ->whereIn('status', [Cart::STATUS_ACCESS_GRANTED, Cart::STATUS_ACCESS_COMPLETE])
            ->where('flags', '&', Cart::FLAGS_HAS_PERIODS)
            ->whereRaw('(flags & ' . Cart::FLAGS_PERIODIC_COMPLETED . ') = 0')
            ->chunk(100, function ($carts) {
                foreach ($carts as $cart) {
                    if (isset($cart->data['periodic_custom']) && count($cart->data['periodic_custom']) > 0) {
                        $this->getInstallmentsForCartPeriodicCustom($cart->customer, $cart);
                    } else if (isset($cart->data['periodic_product_ids'])) {
                        foreach ($cart->data['periodic_product_ids'] as $pid) {
                            if (is_array($pid) && isset($pid['id'])) {
                                $pid = $pid['id'];
                            }
                            $product = Product::find($pid);
                            if (!is_null($product)) {
                                $this->getInstallmentsForProductInCart($cart->customer, $cart, $product);
                            }
                        }
                    }
                }
            });
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     * @return void
     */
    public function getUserBalance(IECommerceUser $user, int $currency)
    {
        return $user->getBalanceAttribute();
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     * @return float
     */
    public function getUserTotalAquiredGiftBalance(IECommerceUser $user, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.gift-balance',
            function () use ($user, $currency) {
                return WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('flags', '&', WalletTransaction::FLAGS_REGISTRATION_GIFT)
                    ->sum('amount');
            },
            ['user.wallet:' . $user->id],
            null
        );
    }


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     * @return float
     */
    public function getUserVirtualBalance(IECommerceUser $user, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.gift-balance',
            function () use ($user, $currency) {
                return WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('type', WalletTransaction::TYPE_VIRTUAL_MONEY)
                    ->sum('amount');
            },
            ['user.wallet:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer|Product $product
     * @return boolean
     */
    public function isProductOnPurchasedList(IECommerceUser $user, $product)
    {
        if (is_numeric($product)) {
            $product = Product::find($product);
        }
        if (is_null($product)) {
            return false;
        }

        $ids = $this->getPurchasedItemIds($user);

        if (!is_null($product->parent_id)) {
            $ancestors = $this->getProductAncestors($product);
        } else {
            $ancestors = [];
        }
        $ancestors[] = $product->id;

        foreach ($ancestors as $parent) {
            if (in_array(is_numeric($parent) ? $parent : $parent->id, $ids)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param Cart $cart
     * @return void
     */
    public function markCartPurchased($request, Cart $cart, $walletTimestamp = null)
    {
        if (is_null($walletTimestamp)) {
            $walletTimestamp = Carbon::now();
        }

        $periodicFlag = isset($cart->data['periodic_product_ids']) && count($cart->data['periodic_product_ids']) > 0 ? Cart::FLAGS_HAS_PERIODS : 0;
        $data = $cart->data;
        if (!isset($data['period_start']) || is_null($data['period_start'])) {
            $data['period_start'] = Carbon::now();
        }
        $cart->update([
            'status' => Cart::STATUS_ACCESS_COMPLETE,
            'flags' => $cart->flags | Cart::FLAGS_EVALUATED | $periodicFlag,
            'data' => $data,
        ]);
        $this->markGiftCodeUsageForCart($cart);
        $supportProfileId = isset($cart->customer->supportProfile['id']) ? $cart->customer->supportProfile['id'] : null;
        $purchasedAt = isset($cart->data['period_start']) ? Carbon::parse($cart->data['period_start']) : $cart->updated_at;
        $user = $cart->customer;

        // give introducer gift
        if (BaseFlags::isActive($cart->flags, Cart::FLAGS_USER_CART)) {
            // if the amount is higher than some value for first purchase only
            if (floatval($cart->amount) >= floatval(config('larapress.ecommerce.lms.introducers.introducer_gift_on.amount'))) {
                /** @var [IECommerceUser,FormEntry] $entry */
                [$introducer, $entry] = $cart->customer->introducerData;
                if (!is_null($introducer)) {
                    if (!$introducer->hasRole(config('larapress.ecommerce.lms.support_role_id'))) {
                        if (!isset($entry->data['gifted_at'])) {
                            $data = $entry->data;
                            $data['gifted_at'] = Carbon::now();
                            $data['gifted_amount'] = config('larapress.ecommerce.lms.introducers.introducer_gift.amount');
                            $data['gifted_currency'] = config('larapress.ecommerce.lms.introducers.introducer_gift.currency');
                            $entry->update([
                                'data' => $data,
                            ]);
                            $this->addBalanceForUser(
                                $introducer,
                                $data['gifted_amount'],
                                $data['gifted_currency'],
                                WalletTransaction::TYPE_VIRTUAL_MONEY,
                                WalletTransaction::FLAGS_REGISTRATION_GIFT,
                                trans('larapress::ecommerce.banking.messages.wallet-descriptions.introducer_gift_purchase_wallet_desc')
                            );
                        }
                    }
                }
            }
        }

        // update original cart if this is a FLAGS_PERIOD_PAYMENT_CART
        if (BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART)) {
            $originalCart = Cart::with('products')->find($cart->data['periodic_pay']['originalCart']);
            $origData = $originalCart->data;
            $now = Carbon::now();

            // custom cart with periodic payments or
            if (isset($cart->data['periodic_pay']['custom'])) {
                if (!isset($origData['periodic_payments_custom'])) {
                    $origData['periodic_payments_custom'] = [];
                }
                $origData['periodic_payments_custom'][] = [
                    'payment_cart' => $cart->id,
                    'payment_date' => $now,
                ];

                $payment_index = $cart->data['periodic_pay']['index'];
                $flags = $originalCart->flags;

                if (!isset($originalCart->data['periodic_custom']) || count($originalCart->data['periodic_custom']) === 0) {
                    Log::critical('Cart custom periodic payment with id '.$cart->id.' original cart id '.$originalCart->id.' is not custom payment');
                    return $cart;
                }

                $customPeriods = array_filter($originalCart->data['periodic_custom'], function($data) {
                    return isset($data['payment_at']) && !is_null($data['payment_at']);
                });
                $map_indexer = 0;
                $periodConfig = array_map(function ($data) use(&$map_indexer) {
                    $data['payment_at'] = Carbon::parse($data['payment_at']);
                    $data['orig_index'] = $map_indexer++;
                    return $data;
                }, $customPeriods);
                $now = Carbon::now();
                usort($periodConfig, function($a, $b) use($now) {
                    return $a['payment_at']->getTimestamp() - $b['payment_at']->getTimestamp();
                });

                $unsorted_index = $periodConfig[$payment_index]['orig_index'];
                if ($payment_index == count($customPeriods)  - 1) { // last period index
                    $flags |= Cart::FLAGS_PERIODIC_COMPLETED;
                } else {
                    $flags = ($flags & ~Cart::FLAGS_PERIODIC_COMPLETED);
                }

                $origData['periodic_custom'][$unsorted_index]['status'] = 1;
                $origData['periodic_custom'][$unsorted_index]['payment_paid_at'] = $now;

                $originalCart->update([
                    'flags' => $flags,
                    'data' => $origData,
                ]);
                CRUDUpdated::dispatch($user, $originalCart, CartCRUDProvider::class, $now);
            } else {
                // or system cart with product based parchases
                $originalProductId = $cart->data['periodic_pay']['product']['id'];
                if (!isset($origData['periodic_payments'])) {
                    $origData['periodic_payments'] = [];
                }
                if (!isset($origData['periodic_payments'][$originalProductId])) {
                    $origData['periodic_payments'][$originalProductId] = [];
                }
                $origData['periodic_payments'][$originalProductId][] = [
                    'payment_cart' => $cart->id,
                    'payment_date' => Carbon::now(),
                ];
                $flags = $originalCart->flags;
                if (count($origData['periodic_payments'][$originalProductId]) >= $cart->data['periodic_pay']['total']) {
                    $flags |= Cart::FLAGS_PERIODIC_COMPLETED;
                } else {
                    $flags = ($flags & ~Cart::FLAGS_PERIODIC_COMPLETED);
                }

                $originalCart->update([
                    'flags' => $flags,
                    'data' => $origData,
                ]);
                CRUDUpdated::dispatch($user, $originalCart, CartCRUDProvider::class, Carbon::now());
            }
        }

        // decrease use wallet cart if this is a STATUS_ACCESS_COMPLETE & some items are being purchased or a period payment
        //      is getting paid for
        if ($cart->status === Cart::STATUS_ACCESS_COMPLETE && !BaseFlags::isActive($cart->flags, Cart::FLAGS_INCREASE_WALLET)) {
            $realMoneyDecrese = abs($cart->amount);
            // separate gift balance from real balance
            $giftBalance = $this->getUserVirtualBalance($cart->customer, $cart->currency);
            $virtualMoneyDecrease = $giftBalance;

            if ($giftBalance > 0) {
                // user has gift more than cart amount
                if ($giftBalance >= $realMoneyDecrese) {
                    $virtualMoneyDecrease = $realMoneyDecrese;
                    $realMoneyDecrese = 0;
                } else {
                    $realMoneyDecrese = $realMoneyDecrese - $virtualMoneyDecrease;
                }
            }

            // calculate products share on virtual and real money
            /** @var Product[] */
            $items = $cart->products;
            $virtualShare = [];
            $realShare = [];

            $usedVirtual = 0;
            $usedReal = 0;
            $periodicIds = isset($cart->data['periodic_product_ids']) ? $cart->data['periodic_product_ids'] : [];
            $giftCode = isset($cart->data['gift_code']) ? $cart->data['gift_code'] : [];
            // use original cart data if this is a period payment
            if (BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART)) {
                $originalCart = Cart::with('products')->find($cart->data['periodic_pay']['originalCart']);
                $originalItems = $originalCart->products;
                $items = [];
                // use original cart gift code
                $giftCode = isset($originalCart->data['gift_code']) ? $originalCart->data['gift_code'] : [];
                if (
                    isset($originalCart->data['periodic_custom']) && count($originalCart->data['periodic_custom']) > 0 &&
                    isset($cart->data['periodic_pay']['custom']) && $cart->data['periodic_pay']['custom']
                ) {
                    $items = $originalItems;
                } else {
                    $originalProductId = $cart->data['periodic_pay']['product']['id'];
                    foreach ($originalItems as $origItem) {
                        if ($originalProductId == $origItem->id) {
                            $items[] = $origItem;
                        }
                    }
                }
            }

            // calculate each product share now
            if (
                // for periodic pays installment or admin single item cart
                BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART) ||
                count($items) === 1
            ) {
                if (count($items) > 0) {
                    $eachVirtaulShare = floor($virtualMoneyDecrease / count($items));
                    $eachRealShare = floor($realMoneyDecrese / count($items));
                    if ($eachVirtaulShare > 0) {
                        foreach ($items as $item) {
                            $virtualShare[$item->id] = $eachVirtaulShare;
                        }
                    }
                    if ($eachRealShare > 0) {
                        foreach ($items as $item) {
                            $realShare[$item->id] = $eachRealShare;
                        }
                    }
                }
            } else {
                // user
                foreach ($items as $item) {
                    $itemPrice = in_array($item->id, $periodicIds) ? $item->pricePeriodic() : $item->price();

                    if ($itemPrice === 0) {
                        continue;
                    }

                    if (isset($giftCode['amount']) && $giftCode['amount'] > 0) {
                        // should we use gift code for item price
                        /** @var GiftCode */
                        $gift = GiftCode::find($giftCode['code']);

                        if (!is_null($gift)) {
                            if ((isset($giftCode['products']) && in_array($item->id, $giftCode['products'])) ||
                                (!isset($giftCode['products']) || count($giftCode['products']) === 0)
                            ) {
                                if ($gift->isPercentGift()) {
                                    $itemPrice = floor((1 - floatval($gift->data['value']) / 100.0) * $itemPrice);
                                }
                            }
                        }
                    }

                    if ($virtualMoneyDecrease > 0 && $virtualMoneyDecrease > $usedVirtual) {
                        if ($virtualMoneyDecrease >= $itemPrice) {
                            $usedVirtual += $itemPrice;
                            $virtualShare[$item->id] = $itemPrice;
                        } else {
                            $usedVirtual = $virtualMoneyDecrease;
                            $virtualShare[$item->id] = $virtualMoneyDecrease;
                            $itemRealShare = $itemPrice - $virtualMoneyDecrease;
                            $realShare[$item->id] = $itemRealShare;
                        }
                    } else if ($realMoneyDecrese > 0 && $realMoneyDecrese > $usedReal) {
                        if (($realMoneyDecrese - $usedReal) >= $itemPrice) {
                            $usedReal += $itemPrice;
                            $realShare[$item->id] = $itemPrice;
                        } else {
                            $realShare[$item->id] = $realMoneyDecrese - $usedReal;
                            $usedReal = $realMoneyDecrese;
                        }
                    }
                }
            }

            if ($virtualMoneyDecrease > 0) {
                // decrease wallet gift amount for cart amount
                $wallet = WalletTransaction::create([
                    'user_id' => $cart->customer_id,
                    'domain_id' => $cart->domain_id,
                    'amount' => -1 * $virtualMoneyDecrease,
                    'currency' => $cart->currency,
                    'type' => WalletTransaction::TYPE_VIRTUAL_MONEY,
                    'created_at' => $walletTimestamp,
                    'updated_at' => $walletTimestamp,
                    'data' => [
                        'cart_id' => $cart->id,
                        'period_start' => $purchasedAt,
                        'description' => trans('larapress::ecommerce.banking.messages.wallet-descriptions.cart_purchased', ['cart_id' => $cart->id]),
                        'balance' => $this->getUserBalance($cart->customer, $cart->currency),
                        'support' => $supportProfileId,
                        'product_shares' => $virtualShare,
                    ]
                ]);
                WalletTransactionEvent::dispatch($wallet, time());
            }

            if ($realMoneyDecrese > 0) {
                // decrease wallet amount for cart amount
                $wallet = WalletTransaction::create([
                    'user_id' => $cart->customer_id,
                    'domain_id' => $cart->domain_id,
                    'amount' => -1 * $realMoneyDecrese,
                    'currency' => $cart->currency,
                    'type' => WalletTransaction::TYPE_REAL_MONEY,
                    'created_at' => $walletTimestamp,
                    'updated_at' => $walletTimestamp,
                    'data' => [
                        'cart_id' => $cart->id,
                        'period_start' => $purchasedAt,
                        'description' => trans('larapress::ecommerce.banking.messages.wallet-descriptions.cart_purchased', ['cart_id' => $cart->id]),
                        'balance' => $this->getUserBalance($cart->customer, $cart->currency),
                        'product_shares' => $realShare,
                    ]
                ]);
                WalletTransactionEvent::dispatch($wallet, time());
            }
        }

        CartPurchasedEvent::dispatch($cart, time());
        CRUDUpdated::dispatch($user, $cart, CartCRUDProvider::class, Carbon::now());
        $this->resetPurchasedCache($cart->customer_id);
        $this->resetPurchasingCache($cart->customer_id);
        $this->resetBalanceCache($cart->customer_id);

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param Cart $cart
     * @param [type] $request
     * @return [float, array]
     */
    protected function getCartAmountWithRequest(IECommerceUser $user, Cart $cart, $request)
    {
        $data = $cart->data;
        if (is_null($data)) {
            $data = [];
        }
        $data['periodic_product_ids'] = [];

        $amount = 0;
        $items = [];

        if (BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART)) {
            $amount = $cart->amount;
        } else {
            $periodIds = [];
            $periods = $request->get('periods', null);
            if (!is_null($periods)) {
                foreach ($periods as $period => $val) {
                    if ($val) {
                        $periodIds[] = $period;
                        $data['periodic_product_ids'][] = $period;
                    }
                }
            }

            $items = $this->getPurchasingCartItems($user, $cart->currency);
            foreach ($items as $item) {
                if (in_array($item->id, $periodIds)) {
                    $amount += $item->pricePeriodic();
                } else {
                    $amount += $item->price();
                }
            }
        }


        $data['use_balance'] = $request->get('use_balance', false);

        return [$amount, $data, $items];
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param Cart $cart
     * @param Request $request
     * @return Cart
     */
    protected function updateCartWithRequest(IECommerceUser $user, Cart $cart, $request)
    {
        [$amount, $data] = $this->getCartAmountWithRequest($user, $cart, $request);

        $data['gift_code'] = null;
        $code = $request->get('gift_code', null);
        if (!is_null($code)) {
            $data['gift_code'] = $this->checkGiftCodeForPurchasingCart($request, $user, $cart->currency, $code);
        }
        if (isset($data['gift_code']['amount'])) {
            $amount -= $data['gift_code']['amount'];
            $amount = max($amount, 0);
        }

        $cart->update([
            'amount' => $amount,
            'data' => $data,
        ]);
        CRUDUpdated::dispatch($user, $cart, CartCRUDProvider::class, Carbon::now());
        $this->resetPurchasedCache($cart->customer_id);
        $this->resetPurchasingCache($cart->customer_id);

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param integer $currency
     * @return void
     */
    public function checkGiftCodeForPurchasingCart(Request $request, IECommerceUser $user, int $currency, string $code)
    {
        /** @var GiftCode */
        $code = GiftCode::query()
            ->with('use_list')
            ->where('code', $code)
            ->where('status', GiftCode::STATUS_AVAILABLE)
            ->first();
        if (is_null($code)) {
            throw new AppException(AppException::ERR_INVALID_PARAMS);
        }
        if (isset($code->data['expires_at'])) {
            $expireAt = Carbon::parse($code->data['expires_at']);
            if (Carbon::now() > $expireAt) {
                throw new AppException(AppException::ERR_NOT_GIFT_EXPIRED);
            }
        }

        $avUsersIds = explode(",", isset($code->data['specific_ids']) ? $code->data['specific_ids'] : "");
        $multiUsePerUser = isset($code->data['multi_time_use']) && $code->data['multi_time_use'] ? true : false;
        if (in_array($user->id, $code->use_list->pluck('user_id')->toArray()) && !in_array($user->id, $avUsersIds) && !$multiUsePerUser) {
            throw new AppException(AppException::ERR_INVALID_PARAMS);
        }

        $cart = $this->getPurchasingCart($user, $currency);
        [$amount, $data, $cartItems] = $this->getCartAmountWithRequest($user, $cart, $request);

        if (isset($code->data['min_items']) && $code->data['min_items'] > 0) {
            if ($code->data['min_items'] > count($cartItems)) {
                throw new AppException(AppException::ERR_NOT_ENOUGHT_ITEMS_IN_CART);
            }
        }

        if (isset($code->data['min_amount']) && $code->data['min_amount'] > 0) {
            if ($code->data['min_amount'] > $amount) {
                throw new AppException(AppException::ERR_NOT_ENOUGHT_AMOUNT_IN_CART);
            }
        }

        $periodIds = [];
        $periods = $request->get('periods', []);
        foreach ($periods as $period => $val) {
            if ($val) {
                $periodIds[] = $period;
            }
        }
        $fixed_only = isset($code->data['fixed_only']) && $code->data['fixed_only'];

        switch ($code->data['type']) {
            case 'percent':
                $percent = floatval($code->data['value']) / 100.0;
                $offProductIds = [];
                if ($percent <= 1) {
                    if (isset($code->data['products'])) {
                        $avCodeProducts = array_keys($code->data['products']);
                        $offAmount = 0;
                        foreach ($avCodeProducts as $avId) {
                            foreach ($cartItems as $item) {
                                if ($item->id === $avId) {
                                    if (!$fixed_only || !in_array($avId, $periodIds)) {
                                        $itemPrice = in_array($avId, $periodIds) ? $item->pricePeriodic() : $item->price();
                                        $offAmount += floor($percent * $itemPrice);
                                        $offProductIds[] = $item->id;
                                    }
                                }
                            }
                        }
                    } else {
                        if ($fixed_only) {
                            $offAmount = 0;
                            foreach ($cartItems as $item) {
                                if (!in_array($periodIds, $item->id)) {
                                    $itemPrice = $item->price();
                                    $offAmount += floor($percent * $itemPrice);
                                    $offProductIds[] = $item->id;
                                }
                            }
                        } else {
                            $offAmount = floor($amount * $percent);
                        }
                    }
                    $offAmount = min($code->amount, $offAmount);
                    return [
                        'amount' => $offAmount,
                        'code' => $code->id,
                        'products' => $offProductIds,
                        'percent' => $percent,
                    ];
                }
            case 'amount':
                $offProductIds = [];
                if (isset($code->data['products'])) {
                    $avCodeProducts = array_keys($code->data['products']);
                    $offAmount = 0;
                    foreach ($avCodeProducts as $avId) {
                        foreach ($cartItems as $item) {
                            if ($item->id === $avId) {
                                $offAmount = floatval($code->data['value']);
                                $offProductIds[] = $item->id;
                            }
                        }
                    }
                } else {
                    $offAmount = floatval($code->data['value']);
                    foreach ($cartItems as $item) {
                        $offProductIds[] = $item->id;
                    }
                }
                $offAmount = min($code->amount, $offAmount);
                return [
                    'amount' => $offAmount,
                    'code' => $code->id,
                    'products' => $offProductIds,
                ];
        }

        throw new AppException(AppException::ERR_OBJ_NOT_READY);
    }

    /**
     * Undocumented function
     *
     * @param Cart $cart
     * @return void
     */
    protected function markGiftCodeUsageForCart(Cart $cart)
    {
        if (isset($cart->data['gift_code']['code'])) {
            GiftCodeUse::create([
                'user_id' => $cart->customer_id,
                'cart_id' => $cart->id,
                'code_id' => $cart->data['gift_code']['code']
            ]);
            $code = GiftCode::find($cart->data['gift_code']['code']);
            if (isset($code->data['one_time_use']) && $code->data['one_time_use']) {
                $code->update([
                    'status' => GiftCode::STATUS_EXPIRED
                ]);
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param int $userId
     * @return void
     */
    protected function resetPurchasingCache($userId)
    {
        Cache::tags(['purchasing-cart:' . $userId])->flush();
    }

    /**
     * Undocumented function
     *
     * @param int $userId
     * @return void
     */
    protected function resetPurchasedCache($userId)
    {
        Cache::tags(['purchased-cart:' . $userId])->flush();
    }

    /**
     * @param int $userId
     * @return void
     */
    protected function resetBalanceCache($userId)
    {
        Cache::tags(['user.wallet:' . $userId])->flush();
    }

    /**
     * Undocumented function
     *
     * @param Product $product
     * @return array
     */
    protected function getProductAncestors($product)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.product-ancestors.' . $product->id,
            function () use ($product) {
                $ancestors = [];
                $parent = $product->parent;
                while (!is_null($parent)) {
                    $ancestors[] = $parent;
                    $parent = $parent->parent;
                }
                return $ancestors;
            },
            ['product.ancestors:' . $product->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $giftCodeId
     * @return mixed
     */
    public function duplicateGiftCodeForRequest(Request $request, $giftCodeId)
    {
        /** @var GiftCode */
        $giftCode = GiftCode::find($giftCodeId);

        if (is_null($giftCode)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        $data = $giftCode->toArray();
        $data['code'] = Helpers::randomString(10);
        unset($data['id']);
        /** @var GiftCode */
        $duplicate = GiftCode::create($data);

        CRUDCreated::dispatch(Auth::user(), $duplicate, GiftCodeCRUDProvider::class, Carbon::now());

        return $duplicate;
    }
}
