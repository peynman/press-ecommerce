<?php

namespace Larapress\ECommerce\Services\Banking;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
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
     * @param string $gateway
     * @param array $data
     * @return IBankPortInterface
     */
    public function getPortInterface(string $gateway, array $data): IBankPortInterface
    {
        $avPorts = config('larapress.ecommerce.banking.ports');

        if (!isset($avPorts[$gateway])) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        $portClass = $avPorts[$gateway];

        app()->bind(IBankPortInterface::class, $portClass);
        return app()->makeWith(IBankPortInterface::class, [
            'config' => $data,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param callback|null $onFailed
     * @param callback|null $onAlreadyPurchased
     *
     * @return Response
     */
    public function redirectToBankForAmount(BankRedirectRequest $request, $onFailed, $onAlreadyPurchased)
    {
        /** @var IECommerceUser */
        $user = Auth::user();
        /** @var Cart */
        $cart = Cart::updateOrCreate([
            'currency' => $request->getCurrency(),
            'customer_id' => $user->id,
            'domain_id' => $user->getMembershipDomainId(),
            'flags' => Cart::FLAGS_INCREASE_WALLET,
            'status' => Cart::STATUS_UNVERIFIED,
        ], [
            'amount' => $request->getAmount(),
            'data' => [
                'success_redirect' => $request->getSuccessRedirect(),
                'canceled_redirect' => $request->getCanceledRedirect(),
                'failed_redirect' => $request->getFailedRedirect(),
            ],
        ]);
        CRUDUpdated::dispatch($user, $cart, CartCRUDProvider::class, Carbon::now());

        return $this->redirectToBankForCart($request, $cart, $onFailed, $onAlreadyPurchased);
    }

    /**
     * @param BankRedirectRequest   $request
     * @param Cart|int $cart
     * @param callable  $onFailed
     * @param callable  $onAlreadyPurchased
     *
     * @return Response
     */
    public function redirectToBankForCart(BankRedirectRequest $request, $cart, $onFailed, $onAlreadyPurchased)
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
            return $onAlreadyPurchased($request, $cart);
        }

        $domain = $user->getMembershipDomain();
        $balance = $this->walletService->getUserBalance($user, $cart->currency);

        /** @var ICartService */
        $cartService = app(ICartService::class);

        $cart->setSuccessRedirect($request->getSuccessRedirect());
        $cart->setFailedRedirect($request->getFailedRedirect());
        $cart->setCanceledRedirect($request->getCanceledRedirect());

        if (($cart->getUseBalance() && $balance >= $cart->amount)
            // check amount as integer
            || $cart->amount == 0
        ) {
            $cartService->markCartPurchased($cart);
            return $onAlreadyPurchased($request, $cart);
        }

        /** @var BankGateway */
        $gatewayData = BankGateway::find($request->getGatewayId());
        if (is_null($gatewayData)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        /** @var IBankPortInterface */
        $port = $this->getPortInterface($gatewayData->type, $gatewayData->data);

        [$portPrice, $portCurrency] = $port->convertForPriceAndCurrency($cart->amount, $cart->currency);
        if ($cart->getUseBalance() && $balance < $cart->amount) {
            $portPrice = $portPrice - $balance;
        }

        // update cart before forwarding to bank
        $flags = $cart->flags;
        $cart->update([
            'data' => $cart->data,
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
                'description' => trans('larapress::ecommerce.banking.messages.bank_forward', [
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
            /** @var BankGatewayTransaction */
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
            /** @var IBankPortInterface */
            $port = $this->getPortInterface($transaction->bank_gateway->type, $transaction->bank_gateway->data);
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
                BankGatewayTransactionEvent::dispatch($transaction, $request->ip(), Carbon::now());
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
