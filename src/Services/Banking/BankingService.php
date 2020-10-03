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
use Larapress\ECommerce\CRUD\BankGatewayCRUDProvider;
use Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
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
use Larapress\Profiles\Models\Domain;
use Larapress\Profiles\Repository\Domain\IDomainRepository;

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
        /** @var IProfileUser */
        $user = Auth::user();
        /** @var Cart */
        $cart = Cart::updateOrCreate([
            'currency' => $currency,
            'customer_id' => $user->id,
            'domain_id' => $user->getMembershipDomainId(),
            'flags' => Cart::FLAG_INCREASE_WALLET,
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
     * @param IProfileUser $user
     * @param array $ids
     * @param int $currency
     * @return Cart
     */
    public function createCartWithProductIDs(Request $request, IProfileUser $user, array $ids, $currency)
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

        /** @var IProfileUser */
        $user = Auth::user();
        if ($user->id !== $cart->customer_id) {
            throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
        }

        if ($cart->isPaid()) {
            return $onAlreadyPurchased($request, $cart);
        }

        $avPorts = config('larapress.ecommerce.banking.ports');
        $gatewayData = BankGateway::find($gateway_id);
        /** @var IBankPortInterface */
        $port = new $avPorts[$gatewayData->type]($gatewayData);

        [$portPrice, $portCurrency] = $port->convertForPriceAndCurrency(floatval($cart->amount), $cart->currency);

        $domain = $user->getMembershipDomain();
        $balance = $this->getUserBalance($user, $cart->currency);

        if ((isset($cart->data['use_balance']) && $cart->data['use_balance'] && floatval($balance['amount']) >= floatval($cart->amount)) || $portPrice == 0) {
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
        } else if (isset($cart->data['use_balance']) && $cart->data['use_balance'] && floatval($balance['amount']) < $cart->amount) {
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
                $wallet = WalletTransaction::create([
                    'user_id' => $cart->customer_id,
                    'domain_id' => $cart->domain_id,
                    // increase wallet amount = transaction amount, becouse
                    // we want to increase user wallet with the amount he just paid
                    // cart amount may differ to this value
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'type' => WalletTransaction::TYPE_BANK_TRANSACTION,
                    'data' => [
                        'cart_id' => $cart->id,
                        'transaction_id' => $transaction->id,
                        'description' => trans('larapress::ecommerce.banking.messages.wallet-descriptions.cart_increased', ['cart_id' => $cart->id]),
                        'balance' => $this->getUserBalance($cart->customer, $cart->currency),
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
    public function updatePurchasingCart(Request $request, IProfileUser $user, int $currency, $cart_id = null)
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
    public function addItemToPurchasingCart(Request $request, IProfileUser $user, ICartItem $cartItem)
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
    public function removeItemFromPurchasingCart(Request $request, IProfileUser $user, ICartItem $cartItem)
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
     * @param IProfileUser $user
     * @param integer $currency
     * @return Cart
     */
    public function getPurchasingCart(IProfileUser $user, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchase-cart',
            function () use ($user, $currency) {
                $cart = Cart::query()
                    ->where('customer_id', $user->id)
                    ->where('domain_id', $user->getMembershipDomainId())
                    ->where('currency', $currency)
                    ->where('flags', '&', Cart::FLAG_USER_CART)
                    ->where('status', '=', Cart::STATUS_UNVERIFIED)
                    ->first();

                if (is_null($cart)) {
                    $cart = Cart::create([
                        'amount' => 0,
                        'currency' => $currency,
                        'customer_id' => $user->id,
                        'domain_id' => $user->getMembershipDomainId(),
                        'flags' => Cart::FLAG_USER_CART,
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
     * @param IProfileUser $user
     * @param integer $currency
     * @return ICartItem[]
     */
    public function getPurchasingCartItems(IProfileUser $user, int $currency)
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
     * @param IProfileUser $user
     * @return array
     */
    public function getPurchasedCarts(IProfileUser $user)
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
     * @param IProfileUser $user
     * @return array
     */
    public function getPurchasedItemIds(IProfileUser $user)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchased-cart-items',
            function () use ($user) {
                $carts = $this->getPurchasedCarts($user);
                $ids = [];
                foreach ($carts as $cart) {
                    foreach ($cart->products as $item) {
                        $ids[] = $item['id'];
                    }
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
     * @param IProfileUser $user
     * @return array
     */
    public function getPeriodicInstallmentsLockedProducts(IProfileUser $user) {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.periodic-products',
            function () use ($user) {
                $carts = $this->getPurchasedCarts($user);
                $ids = [];
                $now = Carbon::now();
                foreach ($carts as $cart) {
                    if (BaseFlags::isActive($cart->flags, Cart::FLAGS_HAS_PERIODS) && !BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIODIC_COMPLETED)) {
                        if (isset($cart->data['periodic_custom'])) {
                            $periodConfig = array_reverse($cart->data['periodic_custom']);
                            $paymentInfo = null;
                            foreach ($periodConfig as $custom) {
                                if (isset($custom['status']) && $custom['status'] == 2) {
                                    $paymentInfo = $custom;
                                    break;
                                }
                            }

                            if (!is_null($paymentInfo) && isset($paymentInfo['payment_at'])) {
                                $payment_at = Carbon::createFromFormat(config('larapress.crud.datetime-format'), $paymentInfo['payment_at']);
                                if ($now > $payment_at) {
                                    foreach ($cart->products as $product) {
                                        $ids[] = $product->id;
                                    }
                                }
                            }
                        } else if (isset($cart->data['periodic_product_ids']) && isset($cart->data['period_start'])) {
                            $periodicProducts = $cart->data['periodic_product_ids'];
                            foreach ($cart->products as $product) {
                                if (in_array($product->id, $periodicProducts)) {
                                    $period_start = Carbon::parse($cart->data['period_start']);
                                    $alreadyPaidPeriods = isset($cart->data['periodic_payments']) ? $cart->data['periodic_payments']: [];
                                    $alreadyPaidCount = isset($alreadyPaidPeriods[$product->id]) ? count($alreadyPaidPeriods[$product->id]) : 0;
                                    $calc = $product->data['calucalte_periodic'];
                                    $duration = isset($calc['period_duration']) ? $calc['period_duration'] : 30;
                                    $period_start->addDays($duration * ($alreadyPaidCount+1) - 1);
                                    if ($now > $period_start) {
                                        $ids[] = $product->id;
                                    }
                                }
                            }
                        }
                    }
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
     * @param IProfileUser $user
     * @param float $amount
     * @param integer $currency
     * @param integer $type
     * @param integer $flags
     * @param string $desc
     * @return [Cart, WalletTransaction]
     */
    public function addBalanceForUser(Request $request, IProfileUser $user, float $amount, int $currency, int $type, int $flags, string $desc)
    {
        $cart = null;

        if ($type === WalletTransaction::TYPE_BANK_TRANSACTION) {
            /** @var Cart */
            $cart = Cart::create([
                'currency' => $currency,
                'customer_id' => $user->id,
                'domain_id' => $user->getMembershipDomainId(),
                'flags' => Cart::FLAG_INCREASE_WALLET | Cart::FLAGS_EVALUATED,
                'status' => Cart::STATUS_ACCESS_COMPLETE,
                'amount' => $amount,
                'data' => [
                    'desc' => $desc,
                ]
            ]);
        }

        $wallet = WalletTransaction::create([
            'user_id' => $user->id,
            'domain_id' => $user->getMembershipDomainId(),
            'amount' => $amount,
            'currency' => $currency,
            'type' => $type,
            'flags' => $flags,
            'data' => [
                'cart_id' => !is_null($cart) ? $cart->id : null,
                'description' => $desc,
                'balance' => $this->getUserBalance($user, $currency),
            ]
        ]);

        if (!is_null($cart)) {
            CRUDUpdated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());
        }
        WalletTransactionEvent::dispatch($wallet, time());
        $this->resetBalanceCache($user->id);

        $user->updateUserCache('balance');

        return [$cart, $wallet];
    }

    /**
     * Undocumented function
     *
     * @param int|Cart $originalCart
     * @param int|Product $product
     * @return Cart
     */
    public function getInstallmentsForProductInCart(IProfileUser $user, $originalCart, $product)
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
        // product is not purchased periodic
        if (!in_array($product->id, $periodicIds)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        // product does not have periodic purchase info
        if (!isset($product->data['calucalte_periodic'])) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }

        $alreadyPaidPeriods = isset($originalCart->data['periodic_payments']) ? $originalCart->data['periodic_payments'] : [];
        $calc = $product->data['calucalte_periodic'];
        $count = $calc['period_count'];
        $alreadyPaidCount = isset($alreadyPaidPeriods[$product->id]) ? count($alreadyPaidPeriods[$product->id]) : 0;
        if ($alreadyPaidCount >= $count) {
            return null;
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
            'amount' => $calc['period_amount'],
            'data' => [
                'periodic_pay' => [
                    'index' => $alreadyPaidCount + 1,
                    'total' => $count,
                    'product' => [
                        'id' => $product->id,
                        'title' => $product->data['title'],
                    ],
                    'originalCart' => $originalCart->id,
                ],
            ]
        ]);

        return $cart;
    }

    /**
     *
     */
    public function getInstallmentsForCartPeriodicCustom(IProfileUser $user, $originalCart) {
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

        $periodConfig = array_reverse($originalCart->data['periodic_custom']);
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

        if ($payment_index >= 0 && !is_null($paymentInfo) && isset($paymentInfo['amount'])) {
            /** @var Cart */
            $cart = Cart::updateOrCreate([
                'currency' => $originalCart->currency,
                'customer_id' => $user->id,
                'domain_id' => $originalCart->domain_id,
                'flags' => Cart::FLAGS_PERIOD_PAYMENT_CART,
                'status' => Cart::STATUS_UNVERIFIED,
                'data->periodic_pay->originalCart' => $originalCart->id,
                'data->periodic_pay->index' => $payment_index,
            ], [
                'amount' => $paymentInfo['amount'],
                'data' => [
                    'periodic_pay' => [
                        'custom' => true,
                        'index' => $payment_index,
                        'total' => $totalPeriods,
                        'originalCart' => $originalCart->id,
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
                    if (isset($cart->data['periodic_custom'])) {
                        $this->getInstallmentsForCartPeriodicCustom($cart->customer, $cart);
                    } else if (isset($cart->data['periodic_product_ids'])) {
                        foreach ($cart->data['periodic_product_ids'] as $pid) {
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
     * @param IProfileUser $user
     * @param integer $currency
     * @return void
     */
    public function getUserBalance(IProfileUser $user, int $currency)
    {
        if (isset($user->cache['balance'])) {
            return $user->cache['balance'];
        }

        return [
            'amount' => 0,
        ];
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.balance',
            function () use ($user, $currency) {
                return WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('domain_id', $user->getMembershipDomainId())
                    ->where('currency', $currency)
                    ->sum('amount');
            },
            ['user.wallet:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param integer $currency
     * @return float
     */
    public function getUserTotalGiftBalance(IProfileUser $user, int $currency) {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.balance',
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
     * @param IProfileUser $user
     * @param integer|Product $product
     * @return boolean
     */
    public function isProductOnPurchasedList(IProfileUser $user, $product)
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
    public function markCartPurchased(Request $request, Cart $cart)
    {
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

        if (BaseFlags::isActive($cart->flags, Cart::FLAG_USER_CART)) {
            $this->markGiftCodeForCart($cart);
            // decrease wallet amount for cart amount
            $wallet = WalletTransaction::create([
                'user_id' => $cart->customer_id,
                'domain_id' => $cart->domain_id,
                'amount' => -1 * abs($cart->amount),
                'currency' => $cart->currency,
                'type' => WalletTransaction::TYPE_BANK_TRANSACTION,
                'data' => [
                    'cart_id' => $cart->id,
                    'description' => trans('larapress::ecommerce.banking.messages.wallet-descriptions.cart_purchased', ['cart_id' => $cart->id]),
                    'balance' => $this->getUserBalance($cart->customer, $cart->currency),
                ]
            ]);
            WalletTransactionEvent::dispatch($wallet, time());

            // give introducer gift, for first purchase only
            // if the amount is higher than some value
            if (floatval($cart->amount) >= floatval(config('larapress.ecommerce.lms.introducers.introducer_gift_on.amount'))) {
                /** @var IProfileUser $introducer */
                /** @var FormEntry $entry */
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
                                $request,
                                $introducer,
                                $data['gifted_amount'],
                                $data['gifted_currency'],
                                WalletTransaction::TYPE_MANUAL_MODIFY,
                                WalletTransaction::FLAGS_REGISTRATION_GIFT,
                                trans('larapress::ecommerce.banking.messages.wallet-descriptions.introducer_gift_purchase_wallet_desc')
                            );
                        }
                    }
                }
            }
        } elseif (BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART)) {
            $originalCart = Cart::find($cart->data['periodic_pay']['originalCart']);
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

                // reverse index
                $payment_index = $cart->data['periodic_pay']['total'] - $cart->data['periodic_pay']['index'] - 1;
                $flags = $originalCart->flags;
                if ($payment_index == 0) { // last period index
                    $flags |= Cart::FLAGS_PERIODIC_COMPLETED;
                }

                $origData['periodic_custom'][$payment_index]['status'] = 1;
                $origData['periodic_custom'][$payment_index]['payment_paid_at'] = $now;

                $originalCart->update([
                    'flags' => $flags,
                    'data' => $origData,
                ]);
                CRUDUpdated::dispatch(Auth::user(), $originalCart, CartCRUDProvider::class, $now);

                // decrease wallet amount for cart amount
                $wallet = WalletTransaction::create([
                    'user_id' => $cart->customer_id,
                    'domain_id' => $cart->domain_id,
                    'amount' => -1 * abs($cart->amount),
                    'currency' => $cart->currency,
                    'type' => WalletTransaction::TYPE_BANK_TRANSACTION,
                    'data' => [
                        'cart_id' => $cart->id,
                        'description' => trans('larapress::ecommerce.banking.messages.wallet-descriptions.cart_purchased', ['cart_id' => $cart->id]),
                        'balance' => $this->getUserBalance($cart->customer, $cart->currency),
                    ]
                ]);
                WalletTransactionEvent::dispatch($wallet, time());
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
                }

                $originalCart->update([
                    'flags' => $flags,
                    'data' => $origData,
                ]);
                CRUDUpdated::dispatch(Auth::user(), $originalCart, CartCRUDProvider::class, Carbon::now());

                // decrease wallet amount for cart amount
                $wallet = WalletTransaction::create([
                    'user_id' => $cart->customer_id,
                    'domain_id' => $cart->domain_id,
                    'amount' => -1 * abs($cart->amount),
                    'currency' => $cart->currency,
                    'type' => WalletTransaction::TYPE_BANK_TRANSACTION,
                    'data' => [
                        'cart_id' => $cart->id,
                        'description' => trans('larapress::ecommerce.banking.messages.wallet-descriptions.cart_purchased', ['cart_id' => $cart->id]),
                        'balance' => $this->getUserBalance($cart->customer, $cart->currency),
                    ]
                ]);
                WalletTransactionEvent::dispatch($wallet, time());
            }
        }
        CartPurchasedEvent::dispatch($cart, time());
        CRUDUpdated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());
        $this->resetPurchasedCache($cart->customer_id);
        $this->resetPurchasingCache($cart->customer_id);
        $this->resetBalanceCache($cart->customer_id);

        // update internal fast cache! for balance
        $cart->customer->updateUserCache('balance');

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Cart $cart
     * @param [type] $request
     * @return [float, array]
     */
    protected function getCartAmountWithRequest(IProfileUser $user, Cart $cart, $request)
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
     * @param IProfileUser $user
     * @param Cart $cart
     * @param Request $request
     * @return Cart
     */
    protected function updateCartWithRequest(IProfileUser $user, Cart $cart, $request)
    {
        [$amount, $data] = $this->getCartAmountWithRequest($user, $cart, $request);

        $data['gift_code'] = null;
        $code = $request->get('gift_code', null);
        if (!is_null($code)) {
            $data['gift_code'] = $this->checkGiftCodeForPurchasingCart($request, Auth::user(), $cart->currency, $code);
        }
        if (isset($data['gift_code']['amount'])) {
            $amount -= $data['gift_code']['amount'];
            $amount = max($amount, 0);
        }

        $cart->update([
            'amount' => $amount,
            'data' => $data,
        ]);
        CRUDUpdated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());
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
    public function checkGiftCodeForPurchasingCart(Request $request, IProfileUser $user, int $currency, string $code)
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
        [$amount, $data, $items] = $this->getCartAmountWithRequest($user, $cart, $request);

        if (isset($code->data['min_items']) && $code->data['min_items'] > 0) {
            if ($code->data['min_items'] > count($items)) {
                throw new AppException(AppException::ERR_NOT_ENOUGHT_ITEMS_IN_CART);
            }
        }

        if (isset($code->data['min_amount']) && $code->data['min_amount'] > 0) {
            if ($code->data['min_amount'] > $amount) {
                throw new AppException(AppException::ERR_NOT_ENOUGHT_AMOUNT_IN_CART);
            }
        }

        switch ($code->data['type']) {
            case 'percent':
                $percent = floatval($code->data['value']) / 100.0;
                if ($percent <= 1) {
                    if (isset($code->data['products'])) {
                        $avCodeProducts = array_keys($code->data['products']);
                        /** @var ICartItem[] */
                        $cartItems = $cart->products;
                        $periodIds = isset($data['periodic_product_ids']) ? $data['periodic_product_ids'] : [];
                        $offAmount = 0;
                        foreach ($avCodeProducts as $avId) {
                            foreach ($cartItems as $item) {
                                if ($item->id === $avId) {
                                    $itemPrice = in_array($avId, $periodIds) ? $item->pricePeriodic() : $item->price();
                                    $offAmount += floor($percent * $itemPrice);
                                }
                            }
                        }
                    } else {
                        $offAmount = floor($amount * $percent);
                    }
                    $offAmount = min($code->amount, $offAmount);
                    return [
                        'amount' => $offAmount,
                        'code' => $code->id,
                    ];
                }
            case 'amount':
                if (isset($code->data['products'])) {
                    $avCodeProducts = array_keys($code->data['products']);
                    $cartItems = $cart->products;
                    $offAmount = 0;
                    foreach ($avCodeProducts as $avId) {
                        foreach ($cartItems as $item) {
                            if ($item->id === $avId) {
                                $offAmount = floatval($code->data['value']);
                            }
                        }
                    }
                } else {
                    $offAmount = floatval($code->data['value']);
                }
                $offAmount = min($code->amount, $offAmount);
                return [
                    'amount' => $offAmount,
                    'code' => $code->id,
                ];
        }

        if (is_null($code)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }
    }

    /**
     * Undocumented function
     *
     * @param Cart $cart
     * @return void
     */
    protected function markGiftCodeForCart(Cart $cart)
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
}
