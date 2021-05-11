<?php

namespace Larapress\ECommerce\Services\Banking;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Larapress\CRUD\BaseFlags;
use Larapress\CRUD\Events\CRUDCreated;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\BankGateway;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\Events\BankGatewayTransactionEvent;
use Larapress\ECommerce\Services\Banking\Events\CartPurchasedEvent;
use Larapress\ECommerce\Services\Banking\Events\WalletTransactionEvent;

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

        if ((isset($cart->data['use_balance']) && $cart->data['use_balance'] && floatval($balance['amount']) >= floatval($cart->amount)) || floatval($cart->amount) === 0) {
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
        $flags = $cart->flags;
        $cart->update([
            'data' => $cartData,
            'flags' => $flags | Cart::FLGAS_FORWARDED_TO_BANK,
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
}
