<?php

namespace Larapress\ECommerce\Services\Banking;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Events\CRUDCreated;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\BankGateway;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Cart\ICartService;
use Larapress\ECommerce\Services\Cart\IPurchasingCartService;
use Larapress\ECommerce\Services\Wallet\IWalletService;
use Larapress\ECommerce\Services\Wallet\WalletTransactionEvent;

class BankingService implements IBankingService
{

    /** @var IWalletService */
    protected $walletService;
    public function __construct(IWalletService $walletService)
    {
        $this->walletService = $walletService;
    }

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
    public function redirectToBankForAmount(Request $request, $gateway_id, $amount, $currency, $onFailed, $onSuccess)
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

        return $this->redirectToBankForCart($request, $cart, $gateway_id, $onFailed, $onSuccess);
    }

    /**
     * @param Request            $request
     * @param Cart|int           $cart
     * @param BankGateway|int    $gateway_id
     * @param callable           $onFailed
     * @param callable           $onSuccess
     *
     * @return Response
     */
    public function redirectToBankForCart(Request $request, $cart, $gateway_id, $onFailed, $onSuccess)
    {
        if (is_numeric($cart)) {
            /** @var Cart $cart */
            $cart = Cart::find($cart);
        }

        /** @var IECommerceUser */
        $user = Auth::user();
        if ($user->id !== $cart->customer_id) {
            throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
        }

        if ($cart->isPaid()) {
            return $onSuccess($request, $cart);
        }

        $domain = $user->getMembershipDomain();
        $balance = $this->walletService->getUserBalance($user, $cart->currency);

        /** @var ICartService */
        $cartService = app(ICartService::class);

        if ((isset($cart->data['use_balance']) && $cart->data['use_balance'] == true && $balance >= $cart->amount)
            // check amount as integer
            || $cart->amount == 0
        ) {
            $cartService->markCartPurchased($cart);
            return $onSuccess($request, $cart);
        }

        $avPorts = config('larapress.ecommerce.banking.ports');
        $gatewayData = BankGateway::find($gateway_id);

        if (is_null($gatewayData)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        if (!isset($avPorts[$gatewayData->type])) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        /** @var IBankPortInterface */
        $port = new $avPorts[$gatewayData->type]($gatewayData);

        [$portPrice, $portCurrency] = $port->convertForPriceAndCurrency($cart->amount, $cart->currency);
        if (isset($cart->data['use_balance']) && $cart->data['use_balance'] && $balance < $cart->amount) {
            $portPrice = $portPrice - $balance;
        }

        // update cart before forwarding to bank
        $return_to = $request->get('return_to', null);
        $cartData = $cart->data;
        $cartData['return_to'] = $return_to;
        $flags = $cart->flags;
        $cart->update([
            'data' => $cartData,
            'flags' => $flags | Cart::FLGAS_FORWARDED_TO_BANK,
        ]);

        /** @var IPurchasingCartService */
        $purchasingService = app(IPurchasingCartService::class);
        $purchasingService->resetPurchasingCache($user->id);

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
                'description' => trans('larepress::ecommerce.banking.messages.bank_forwared', [
                    'cart_id' => $cart->id
                ]),
            ]
        ]);
        // reference keeping for redirect
        /// no logic here; just keep objects in memory update and ready
        $transaction->bank_gateway = $gatewayData;
        $transaction->domain = $domain;
        $transaction->customer = $user;

        BankGatewayTransactionEvent::dispatch($transaction, $request->ip(), Carbon::now());
        CRUDCreated::dispatch($user, $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

        // callback url for this transaction port from banking port
        $callback = route(config('larapress.ecommerce.routes.bank_gateways.name') . '.any.callback', [
            'tr_id' => $transaction->id,
        ]);
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

        /** @var ICartService */
        $cartService = app(ICartService::class);

        try {
            $avPorts = config('larapress.ecommerce.banking.ports');
            $gatewayData = $transaction->bank_gateway;
            /** @var IBankPortInterface */
            $port = new $avPorts[$gatewayData->type]($gatewayData);
            $transaction = $port->verify($request, $transaction);

            if ($transaction->status === BankGatewayTransaction::STATUS_SUCCESS) {
                $this->walletService->addBalanceForUser(
                    $cart->customer,
                    $transaction->amount,
                    $transaction->currency,
                    WalletTransaction::TYPE_REAL_MONEY,
                    WalletTransaction::FLAGS_BALANCE_PURCHASED,
                    trans('larapress::ecommerce.banking.messages.wallet_descriptions.cart_increased', ['cart_id' => $cart->id]),
                    [
                        'cart_id' => $cart->id,
                    ]
                );
                $cartService->markCartPurchased($cart);
                BankGatewayTransactionEvent::dispatch($transaction, $request->ip, Carbon::now());
                return $onSuccess($request, $cart, $transaction);
            } else {
                $transaction->update([
                    'status' => BankGatewayTransaction::STATUS_CANCELED,
                ]);
                BankGatewayTransactionEvent::dispatch($transaction, $request->ip(), Carbon::now());
                CRUDUpdated::dispatch(Auth::user(), $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

                return $onCancel($request, $cart, 'Bank Request Canceled');
            }
        } catch (\Exception $e) {
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
            BankGatewayTransactionEvent::dispatch($transaction, $request->ip(), Carbon::now());
            CRUDUpdated::dispatch(Auth::user(), $transaction, BankGatewayTransactionCRUDProvider::class, Carbon::now());

            return $onFailed($request, $cart, $e->getMessage());
        }
    }
}
