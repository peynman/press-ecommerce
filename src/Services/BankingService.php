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
    public function redirectToBankForAmount(Request $request, $gateway_id, $amount, $currency, $onFailed) {
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
            'data' => [
            ]
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
	public function redirectToBankForCart(Request $request, $cart, $gateway_id, $onFailed) {
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
        $callback = route(config('larapress.ecommerce.routes.bank_gatewayes.name').'.any.callback', [
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
	public function verifyBankRequest(Request $request, $transaction, $onAlreadyPurchased, $onSuccess, $onFailed) {
		if (is_numeric($transaction)) {
			/** @var Cart $cart */
            $transaction = BankGatewayTransaction::with(['cart', 'customer', 'bank_gateway'])->find($transaction);
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
    public function addItemToPurchasingCart(Request $request, ICartItem $cartItem) {
        /** @var IProfileUser */
        $user = Auth::user();
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getRequestDomain($request);
        /** @var Cart */
        $cart = $this->getPurchasingCart($request, $user, $domain, $cartItem->currency());

        $cart->items()->attach($cartItem->model());

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param ICartItem $cartItem
     * @return Cart
     */
    public function removeItemFromPurchasingCart(Request $request, $cartItem) {
        /** @var IProfileUser */
        $user = Auth::user();
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getRequestDomain($request);
        /** @var Cart */
        $cart = $this->getPurchasingCart($request, $user, $domain, $cartItem->currency());

        $cart->items()->detatch($cartItem->model());

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
    protected function getPurchasingCart(Request $request, IProfileUser $user, Domain $domain, int $currency) {
        $cacheName = 'larapress.ecommerce.user.'.$user->id.'.purchase-cart';
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
                'currency' => 0,
                'customer_id' => $user->id,
                'domain_id' => $domain->id,
                'flags' => Cart::FLAG_USER_CART,
                'status' => Cart::STATUS_UNVERIFIED,
                'data' => [
                ]
            ]);

            Cache::put($cacheName, $cart);
        }

        return $cart;
    }
}
