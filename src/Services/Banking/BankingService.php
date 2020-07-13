<?php

namespace Larapress\ECommerce\Services\Banking;


use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Larapress\CRUD\BaseFlags;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
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
        $cart = Cart::create([
            'amount' => $amount,
            'currency' => $currency,
            'customer_id' => $user->id,
            'domain_id' => $domain->id,
            'flags' => Cart::FLAG_INCREASE_WALLET,
            'status' => Cart::STATUS_UNVERIFIED,
            'data' => []
        ]);

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
            if (!is_null($onFailed)) {
                return $onFailed($cart, trans('dashboard.errors.cart.already_purchased'));
            } else {
                return [
                    'message' => trans('dashboard.errors.cart.already_purchased')
                ];
            }
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

        BankGatewayTransactionEvent::dispatch($domain, $request->ip(), time(), $transaction);

        // reference keeping for redirect
        /// no logic here; just keep objects in memory update and ready
        $transaction->bank_gateway = $gatewayData;
        $transaction->domain = $domain;

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
    public function verifyBankRequest(Request $request, $transaction, $onAlreadyPurchased, $onSuccess, $onFailed)
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
                        'description' => trans('larapress::ecommerce.banking.messages.wallet-descriptions.cart_increased', ['cart_id' => $cart->id])
                    ]
                ]);
                $this->markCartPurchased($request, $cart);
                DB::commit();

                $this->resetPurchasedCache($cart->customer, $cart->domain);
                WalletTransactionEvent::dispatch($cart->domain, $request->ip(), time(), $wallet);
                BankGatewayTransactionEvent::dispatch($cart->domain, $request->ip(), time(), $transaction);
                Cache::tags(['wallet:' . $cart->customer_id])->flush();
                return $onSuccess($request, $cart, $transaction);
            } else {
                $transaction->update([
                    'status' => BankGatewayTransaction::STATUS_CANCELED,
                ]);
                DB::commit();

                BankGatewayTransactionEvent::dispatch($transaction->domain, $request->ip(), time(), $transaction);
                return $onFailed($request, $cart, 'Bank Request Canceled');
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
    public function updatePurchasingCart(Request $request, int $currency)
    {
        /** @var IProfileUser */
        $user = Auth::user();
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getRequestDomain($request);

        $cart = $this->getPurchasingCart($user, $domain, $currency);
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

        $cart->products()->attach($cartItem->model(), [
            'amount' => $cartItem->price(),
            'currency' => $cartItem->currency(),
        ]);
        $cart->update([
            'amount' => $cart->amount + $cartItem->price()
        ]);
        $this->resetPurchasingCache($user, $domain);

        $cart['items'] = $cart->products()->get();

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

        $periodicIds = $cart->data['periodic_product_ids'];
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
        $periodicFlag = (isset($cart->data['periodic_product_ids']) && count($cart->data['periodic_product_ids']) > 0) ? Cart::FLAGS_HAS_PERIODS : 0;
        $cart->update([
            'status' => Cart::STATUS_ACCESS_COMPLETE,
            'flags' =>
            $cart->flags | Cart::FLAGS_EVALUATED | $periodicFlag,
        ]);
        if (BaseFlags::isActive($cart->flags, Cart::FLAG_USER_CART)) {
            $this->markGiftCodeForCart($cart);
            $wallet = WalletTransaction::create([
                'user_id' => $cart->customer_id,
                'domain_id' => $cart->domain_id,
                'amount' => -1 * $cart->amount,
                'currency' => $cart->currency,
                'type' => WalletTransaction::TYPE_BANK_TRANSACTION,
                'data' => [
                    'cart_id' => $cart->id,
                    'description' => trans('larapress::ecommerce.banking.messages.wallet-descriptions.cart_increased', ['cart_id' => $cart->id])
                ]
            ]);
            WalletTransactionEvent::dispatch($cart->domain, $request->ip(), time(), $wallet);
            CartPurchasedEvent::dispatch($cart->domain, $request->ip(), time(), $cart);
        }

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
