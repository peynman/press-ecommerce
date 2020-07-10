<?php

namespace Larapress\ECommerce\Services;


use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\Models\BankGateway;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
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

        $cart = $transaction->cart;

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
                    'flags' => Cart::FLAGS_EVALUATED | (isset($cart->data['periodic_product_ids']) && count($cart->data['periodic_product_ids']) > 0) ? Cart::FLAGS_HAS_PERIODS : 0,
                ]);
                $this->resetPurchasedCache($transaction->customer, $transaction->domain);
                DB::commit();

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

        $items = $this->getPurchasingCartItems($user, $domain, $currency);
        foreach ($items as $item) {
            if (in_array($item->id, $periodIds)) {
                $amount += $item->pricePeriodic();
            } else {
                $amount += $item->price();
            }
        }

        $cart->update([
            'amount' => $amount,
            'data' => $data,
        ]);

        $balance = $this->getUserBalance($user, $domain, $currency);

        $cart = $this->resetPurchasingCache($user, $domain);

        return [
            'cart' => [
                'amount' => $cart->amount,
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
                    ->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED, Cart::STATUS_ACCESS_PERIOD])
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
     * @return Cart
     */
    protected function resetPurchasingCache(IProfileUser $user, Domain $domain)
    {
        Cache::tags(['purchasing-cart:' . $user->id])->flush();

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
    protected function resetPurchasedCache(IProfileUser $user, Domain $domain)
    {
        Cache::tags(['purchasing-cart:' . $user->id])->flush();
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
