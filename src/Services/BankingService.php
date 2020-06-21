<?php

namespace Larapress\ECommerce\Services;


use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\Models\BankGateway;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\WalletTransaction;
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
    public function redirectToBankForAmount(Request $request, $gateway_id, $amount, $currency, $onFailed)
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

        return $this->redirectToBankForCart($request, $cart, $gateway_id, $onFailed);
    }

    /**
     * @param Request            $request
     * @param Cart|int           $cart
     * @param BankGateway|int    $gateway_id
     * @param callable           $onFailed
     *
     * @return Response
     */
    public function redirectToBankForCart(Request $request, $cart, $gateway_id, $onFailed)
    {
        if (is_numeric($cart)) {
            /** @var Cart $cart */
            $cart = Cart::with(['items'])->find($cart);
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

        [$portPrice, $portCurrency] = $port->convertForPriceAndCurrency($cart->price, $cart->currency);

        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getRequestDomain($request);

        /** @var BankGatewayTransaction */
        $transaction = BankGatewayTransaction::create([
            'price' => $portPrice,
            'currency' => $portCurrency,
            'cart_id' => $cart->id,
            'customer_id' => $user->id,
            'bank_gateway_id' => $gatewayData->id,
            'domain_id' => $domain->id,
            'status' => BankGatewayTransaction::STATUS_CREATED,
            'data' => [
                'description' => trans('larapress::ecommerce.banking.message.bank-forwarded', [
                    'amount' => $portPrice,
                    'currecny' => $portCurrency,
                    'cart_id' => $cart->id
                ])
            ]
        ]);
        $callback = route(config('larapress.ecommerce.routes.bank_gatewayes.name') . '.any.callback', [
            'tr_id' => $transaction->id,
        ]);

        event(new BankTransactionEvent($transaction, $request->ip()));

        // reference keeping for redirect
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

        if ($cart->isPaid()) {
            return $onAlreadyPurchased($request, $cart, $transaction);
        }

        try {
            $avPorts = config('larapress.ecommerce.banking.ports');
            $gatewayData = $transaction->bank_gateway;
            /** @var IBankPortInterface */
            $port = new $avPorts[$gatewayData->type]($gatewayData);

            if ($port->verify($request, $transaction)) {
                DB::beginTransaction();

                $cart->update([
                    'status' => Cart::STATUS_ACCESS_COMPLETE,
                ]);
                $this->resetPurchasedCache($transaction->customer, $transaction->domain);

                event(new BankTransactionEvent($transaction, $request->ip()));

                return $onSuccess($request, $cart, $transaction);
            } else {
                return $onFailed($request, $cart, 'Bank Request Canceled');
            }
        } catch (\Exception $e) {
            $data = $transaction->data;
            $data['exception_message'] = $e->getMessage();
            $transaction->update([
                'status' => BankGatewayTransaction::STATUS_FAILED,
                'data' => $data,
            ]);
            event(new BankTransactionEvent($transaction, $request->ip()));

            DB::rollBack();
            return $onFailed($request, $cart, $e->getMessage());
        }
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

        return $this->resetPurchasingCache($user, $domain);
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

        return $this->resetPurchasingCache($user, $domain);
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
        $cacheName = 'larapress.ecommerce.user.' . $user->id . '.purchase-cart';
        $cart = Cache::get($cacheName);
        if (is_null($cart)) {
            $cart = Cart::query()
                ->where('customer_id', $user->id)
                ->where('domain_id', $domain->id)
                ->where('currency', $currency)
                ->where('flags', '&', Cart::FLAG_USER_CART)
                ->where('status', '=', Cart::STATUS_UNVERIFIED)
                ->first();

        }
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

            Cache::put($cacheName, $cart);
        }

        return $cart;
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
        $cacheName = 'larapress.ecommerce.user.' . $user->id . '.purchase-cart-items';
        $items = Cache::get($cacheName);
        if (is_null($items)) {
            $cart = $this->getPurchasingCart($user, $domain, $currency);
            /** ICartItem[] */
            $items = $cart->products;
            $cacheItems = [];
            foreach ($items as $item) {
                $cacheItems[] = $item->model();
            }
            Cache::put($cacheName, $items);
            $items = $cacheItems;
        }

        return $items;
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
        $cacheName = 'larapress.ecommerce.user.' . $user->id . '.purchased-cart';
        $carts = Cache::get($cacheName);
        if (is_null($carts)) {
            $carts = Cart::query()
                ->with(['products'])
                ->where('customer_id', $user->id)
                ->where('domain_id', $domain->id)
                ->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED, Cart::STATUS_ACCESS_PERIOD])
                ->get();

            Cache::put($cacheName, $carts);
        }

        return $carts;
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
        $cacheName = 'larapress.ecommerce.user.' . $user->id . '.purchased-cart-items';
        $ids = Cache::get($cacheName);
        if (is_null($ids)) {

            $carts = $this->getPurchasedCarts($user, $domain);
            $ids = [];
            foreach ($carts as $cart) {
                foreach($cart->items as $item) {
                    $ids[] = $item['id'];
                }
            }

            Cache::put($cacheName, $ids);
        }

        return $ids;
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
        $cacheName = 'larapress.ecommerce.user.' . $user->id . '.balance';
        $balance = Cache::get($cacheName);
        if (is_null($balance)) {
            $balance = WalletTransaction::query()
                ->where('user_id', $user->id)
                ->where('domain_id', $domain->id)
                ->where('currency', $currency)
                ->sum('amount');

            Cache::put($cacheName, $balance);
        }

        return $balance;
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @param integer $productId
     * @return boolean
     */
    public function isProductOnPurchasedList(IProfileUser $user, Domain $domain, int $productId)
    {
        $ids = $this->getPurchasedItemIds($user, $domain);
        return in_array($productId, $ids);
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @return void
     */
    protected function resetPurchasingCache(IProfileUser $user, Domain $domain) {
        $cacheNames =  [
            'larapress.ecommerce.user.' . $user->id . '.purchase-cart-items',
            'larapress.ecommerce.user.' . $user->id . '.purchase-cart'
        ];

        foreach($cacheNames as $name) {
            Cache::forget($name);
        }

        $cart = $this->getPurchasingCart($user, $domain, config('larapress.ecommerce.banking.currency.id'));
        $cart['items'] = $this->getPurchasingCartItems($user, $domain, config('larapress.ecommerce.banking.currency.id'));
        return $cart;
    }


    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @return void
     */
    protected function resetPurchasedCache(IProfileUser $user, Domain $domain) {
        $cacheNames =  [
            'larapress.ecommerce.user.' . $user->id . '.purchased-cart-items',
            'larapress.ecommerce.user.' . $user->id . '.purchased-cart'
        ];

        foreach($cacheNames as $name) {
            Cache::forget($name);
        }

        $this->getPurchasedCarts($user, $domain, config('larapress.ecommerce.banking.currency.id'));
        $this->getPurchasedItemIds($user, $domain, config('larapress.ecommerce.banking.currency.id'));
    }
}
