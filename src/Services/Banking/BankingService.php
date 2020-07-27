<?php

namespace Larapress\ECommerce\Services\Banking;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getRequestDomain($request);
        /** @var Cart */
        $cart = Cart::updateOrCreate([
            'currency' => $currency,
            'customer_id' => $user->id,
            'domain_id' => $domain->id,
            'flags' => Cart::FLAG_INCREASE_WALLET,
            'status' => Cart::STATUS_UNVERIFIED,
        ],[
            'amount' => $amount,
            'data' => []
        ]);
        CRUDUpdated::dispatch($cart, CartCRUDProvider::class, Carbon::now());


        return $this->redirectToBankForCart($request, $cart, $gateway_id, $onFailed, $onAlreadyPurchased);
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

        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getRequestDomain($request);

        $balance = $this->getUserBalance($user, $domain, $cart->currency);

        if ((isset($cart->data['use_balance']) && $cart->data['use_balance'] && $balance >= $cart->amount) || $portPrice == 0) {
            try {
                DB::beginTransaction();
                $this->markCartPurchased($request, $cart);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return $onFailed($request, $cart, $e);
            }


            $this->resetPurchasedCache($cart->customer, $cart->domain);
            return $onAlreadyPurchased($request, $cart);
        } else if (isset($cart->data['use_balance']) && $cart->data['use_balance'] && $balance < $cart->amount) {
            $portPrice = $portPrice - $balance;
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
        CRUDCreated::dispatch($transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());


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
                        'balance' => $this->getUserBalance($cart->customer, $cart->domain, $cart->currency),
                    ]
                ]);
                $this->markCartPurchased($request, $cart);
                DB::commit();

                $this->resetPurchasedCache($cart->customer, $cart->domain);
                WalletTransactionEvent::dispatch($cart->domain, $request->ip(), time(), $wallet);
                BankGatewayTransactionEvent::dispatch($cart->domain, $request->ip(), time(), $transaction);
                CRUDUpdated::dispatch($transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

                Cache::tags(['wallet:' . $cart->customer_id])->flush();
                return $onSuccess($request, $cart, $transaction);
            } else {
                $transaction->update([
                    'status' => BankGatewayTransaction::STATUS_CANCELED,
                ]);
                DB::commit();

                BankGatewayTransactionEvent::dispatch($transaction->domain, $request->ip(), time(), $transaction);
                CRUDUpdated::dispatch($transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

                return $onCancel($request, $cart, 'Bank Request Canceled');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            $data = $transaction->data;
            $data['exception_message'] = $e->getMessage();
            $transaction->update([
                'status' => BankGatewayTransaction::STATUS_FAILED,
                'data' => $data,
            ]);
            BankGatewayTransactionEvent::dispatch($transaction->domain, $request->ip(), time(), $transaction);
            CRUDUpdated::dispatch($transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

            return $onFailed($request, $cart, $e->getMessage());
        }
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param integer $currency
     * @return void
     */
    public function checkGiftCodeForPurchasingCart(Request $request, int $currency, string $code)
    {
        /** @var IProfileUser */
        $user = Auth::user();
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getRequestDomain($request);

        /** @var GiftCode */
        $code = GiftCode::query()
            ->with('use_list')
            ->where('code', $code)
            ->where('status', GiftCode::STATUS_AVAILABLE)
            ->first();
        if (is_null($code)) {
            throw new AppException(AppException::ERR_INVALID_PARAMS);
        }
        if (in_array($user->id, $code->use_list->pluck('user_id')->toArray())) {
            throw new AppException(AppException::ERR_INVALID_PARAMS);
        }

        $cart = $this->getPurchasingCart($user, $domain, $currency);
        [$amount, $data] = $this->getCartAmountWithRequest($user, $domain, $cart, $request);

        switch ($code->data['type']) {
            case 'percent':
                $percent = floatval($code->data['value']) / 100.0;
                if ($percent <= 1) {
                    $offAmount = $amount * $percent;
                    $offAmount = min($amount, $offAmount);
                    return [
                        'amount' => $offAmount,
                        'code' => $code->id,
                    ];
                }
            case 'amount':
                return [
                    'amount' => min(floatval($code->data['value']), $code->amount),
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
     * @param Request $request
     * @param int $cart_id
     * @return Response
     */
    public function updatePurchasingCart(Request $request, int $currency, $cart_id = null)
    {
        /** @var IProfileUser */
        $user = Auth::user();
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getRequestDomain($request);

        $cart = is_null($cart_id) ? $this->getPurchasingCart($user, $domain, $currency) : Cart::find($cart_id);
        if (is_null($cart) || $cart->customer_id !== $user->id) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        // calls for CRUDUpdate on cart inside
        $this->updateCartWithRequest($user, $domain, $cart, $request);
        $balance = $this->getUserBalance($user, $domain, $currency);

        $this->resetPurchasingCache($user, $domain);
        return [
            'cart' => [
                'amount' => !$cart->data['use_balance'] ?  $cart->amount : max(0, $cart->amount - $balance),
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
    public function addItemToPurchasingCart(Request $request, ICartItem $cartItem)
    {
        /** @var IProfileUser */
        $user = Auth::user();
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getRequestDomain($request);
        /** @var Cart */
        $cart = $this->getPurchasingCart($user, $domain, $cartItem->currency());

        $existingItems = $this->getPurchasingCartItems($user, $domain, $cartItem->currency());
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
        $this->resetPurchasingCache($user, $domain);
        $cart['items'] = $cart->products()->get();
        CRUDUpdated::dispatch($cart, CartCRUDProvider::class, Carbon::now());

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param ICartItem $cartItem
     * @return Cart
     */
    public function removeItemFromPurchasingCart(Request $request, ICartItem $cartItem)
    {
        /** @var IProfileUser */
        $user = Auth::user();
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getRequestDomain($request);
        /** @var Cart */
        $cart = $this->getPurchasingCart($user, $domain, $cartItem->currency());

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

        $this->resetPurchasingCache($user, $domain);
        $cart['items'] = $cart->products()->get();
        CRUDUpdated::dispatch($cart, CartCRUDProvider::class, Carbon::now());

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IProfileUser $user
     * @param Domain $domain
     * @param integer $currency
     * @return Cart
     */
    public function getPurchasingCart(IProfileUser $user, Domain $domain, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchase-cart',
            function () use ($user, $domain, $currency) {
                $cart = Cart::query()
                    ->where('customer_id', $user->id)
                    ->where('domain_id', $domain->id)
                    ->where('currency', $currency)
                    ->where('flags', '&', Cart::FLAG_USER_CART)
                    ->where('status', '=', Cart::STATUS_UNVERIFIED)
                    ->first();

                if (is_null($cart)) {
                    $cart = Cart::create([
                        'amount' => 0,
                        'currency' => $currency,
                        'customer_id' => $user->id,
                        'domain_id' => $domain->id,
                        'flags' => Cart::FLAG_USER_CART,
                        'status' => Cart::STATUS_UNVERIFIED,
                        'data' => []
                    ]);
                }
                return $cart;
            },
            ['user:' . $user->id, 'purchasing-cart:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IProfileUser $user
     * @param Domain $domain
     * @param integer $currency
     * @return ICartItem[]
     */
    public function getPurchasingCartItems(IProfileUser $user, Domain $domain, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchase-cart-items',
            function () use ($user, $domain, $currency) {
                $cart = $this->getPurchasingCart($user, $domain, $currency);
                /** ICartItem[] */
                $items = $cart->products;
                $cacheItems = [];
                foreach ($items as $item) {
                    $cacheItems[] = $item->model();
                }
                return $cacheItems;
            },
            ['user:' . $user->id, 'purchasing-cart:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @return array
     */
    public function getPurchasedCarts(IProfileUser $user, Domain $domain)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchased-cart',
            function () use ($user, $domain) {
                return  Cart::query()
                    ->with(['products'])
                    ->where('customer_id', $user->id)
                    ->where('domain_id', $domain->id)
                    ->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED])
                    ->get();
            },
            ['user:' . $user->id, 'purchased-cart:' . $user->id],
            null,
        );
    }

    /**
     * Undocumented function
     *
     * @param int|Cart $originalCart
     * @param int|Product $product
     * @return Cart
     */
    public function getInstallmentsForProductInCart(IProfileUser $user, $originalCart, $product) {
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
        if (!in_array($product->id, $periodicIds)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

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
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @return array
     */
    public function getPurchasedItemIds(IProfileUser $user, Domain $domain)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchased-cart-items',
            function () use ($user, $domain) {
                $carts = $this->getPurchasedCarts($user, $domain);
                $ids = [];
                foreach ($carts as $cart) {
                    foreach ($cart->products as $item) {
                        $ids[] = $item['id'];
                    }
                }
                return $ids;
            },
            ['user:' . $user->id, 'purchased-cart:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @param integer $currency
     * @return void
     */
    public function getUserBalance(IProfileUser $user, Domain $domain, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.balance',
            function () use ($user, $domain, $currency) {
                return WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('domain_id', $domain->id)
                    ->where('currency', $currency)
                    ->sum('amount');
            },
            ['user:' . $user->id, 'wallet:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @param integer|Product $product
     * @return boolean
     */
    public function isProductOnPurchasedList(IProfileUser $user, Domain $domain, $product)
    {
        if (is_numeric($product)) {
            $product = Product::find($product);
        }
        if (is_null($product)) {
            return false;
        }

        $ids = $this->getPurchasedItemIds($user, $domain);

        if (!is_null($product->parent_id)) {
            $ancestors = $this->getProductAncestors($product);
        } else {
            $ancestors = [];
        }
        $ancestors[] = $product->id;

        foreach ($ancestors as $parent) {
            if (in_array($parent, $ids)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @param Cart $cart
     * @param [type] $request
     * @return [float, array]
     */
    protected function getCartAmountWithRequest(IProfileUser $user, Domain $domain, Cart $cart, $request)
    {
        $data = $cart->data;
        if (is_null($data)) {
            $data = [];
        }
        $data['periodic_product_ids'] = [];

        $amount = 0;

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

            $items = $this->getPurchasingCartItems($user, $domain, $cart->currency);
            foreach ($items as $item) {
                if (in_array($item->id, $periodIds)) {
                    $amount += $item->pricePeriodic();
                } else {
                    $amount += $item->price();
                }
            }
        }


        $data['use_balance'] = $request->get('use_balance', false);

        return [$amount, $data];
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @param Cart $cart
     * @param Request $request
     * @return Cart
     */
    protected function updateCartWithRequest(IProfileUser $user, Domain $domain, Cart $cart, $request)
    {

        [$amount, $data] = $this->getCartAmountWithRequest($user, $domain, $cart, $request);

        $data['gift_code'] = null;
        $code = $request->get('gift_code', null);
        if (!is_null($code)) {
            $data['gift_code'] = $this->checkGiftCodeForPurchasingCart($request, $cart->currency, $code);
        }
        if (isset($data['gift_code']['amount'])) {
            $amount -= $data['gift_code']['amount'];
            $amount = max($amount, 0);
        }

        $cart->update([
            'amount' => $amount,
            'data' => $data,
        ]);
        CRUDUpdated::dispatch($cart, CartCRUDProvider::class, Carbon::now());

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param Cart $cart
     * @return void
     */
    protected function markCartPurchased(Request $request, Cart $cart)
    {
        $periodicFlag = isset($cart->data['periodic_product_ids']) && count($cart->data['periodic_product_ids']) > 0 ? Cart::FLAGS_HAS_PERIODS : 0;
        $data = $cart->data;
        $data['period_start'] = Carbon::now();
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
                    'description' => trans('larapress::ecommerce.banking.messages.wallet-descriptions.cart_increased', ['cart_id' => $cart->id]),
                    'balance' => $this->getUserBalance($cart->customer, $cart->domain, $cart->currency),
                ]
            ]);
            WalletTransactionEvent::dispatch($cart->domain, $request->ip(), time(), $wallet);
        } elseif (BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART)) {
            $originalCart = Cart::find($cart->data['periodic_pay']['originalCart']);
            $origData = $originalCart->data;
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

            $originalCart->update([
                'data' => $origData,
            ]);
            // decrease wallet amount for cart amount
            $wallet = WalletTransaction::create([
                'user_id' => $cart->customer_id,
                'domain_id' => $cart->domain_id,
                'amount' => -1 * abs($cart->amount),
                'currency' => $cart->currency,
                'type' => WalletTransaction::TYPE_BANK_TRANSACTION,
                'data' => [
                    'cart_id' => $cart->id,
                    'description' => trans('larapress::ecommerce.banking.messages.wallet-descriptions.cart_increased', ['cart_id' => $cart->id]),
                    'balance' => $this->getUserBalance($cart->customer, $cart->domain, $cart->currency),
                ]
            ]);
            WalletTransactionEvent::dispatch($cart->domain, $request->ip(), time(), $wallet);
        }
        CartPurchasedEvent::dispatch($cart->domain, $request->ip(), time(), $cart);
        CRUDUpdated::dispatch($cart, CartCRUDProvider::class, Carbon::now());

        return $cart;
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
     * @param IProfileUser $user
     * @param Domain $domain
     * @return void
     */
    protected function resetPurchasingCache(IProfileUser $user, Domain $domain)
    {
        Cache::tags(['purchasing-cart:' . $user->id])->flush();
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @return void
     */
    protected function resetPurchasedCache(IProfileUser $user, Domain $domain)
    {
        Cache::tags(['purchased-cart:' . $user->id])->flush();
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
            ['products', 'product:' . $product->id],
            null
        );
    }
}
