<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Banking\BankRedirectRequest;
use Larapress\ECommerce\Services\Banking\IBankingService;

/**
 * @group Redirect to bank gateways
 */
class BankGatewayController extends Controller
{
    public static function registerPublicWebRoutes()
    {
        Route::post(config('larapress.ecommerce.routes.bank_gateways.name') . '/increase', '\\' . self::class . '@redirectToBankForIncreaseAmount')
            ->name(config('larapress.ecommerce.routes.bank_gateways.name') . '.any.redirect.increase');
        Route::post(config('larapress.ecommerce.routes.bank_gateways.name') . '/checkout', '\\' . self::class . '@redirectToBankForCart')
            ->name(config('larapress.ecommerce.routes.bank_gateways.name') . '.any.redirect.cart');

        Route::any(config('larapress.ecommerce.routes.bank_gateways.name') . '/callback/{tr_id}', '\\' . self::class . '@callbackFromBank')
            ->name(config('larapress.ecommerce.routes.bank_gateways.name') . '.any.callback');
    }

    /**
     * Undocumented function
     *
     * @param IBankingService $service
     * @param Request $request
     * @param string $gateway_id
     * @param string $cart_id
     * @return void
     */
    public function redirectToBankForIncreaseAmount(IBankingService $service, BankRedirectRequest $request)
    {
        return $service->redirectToBankForAmount(
            $request,
            // failed to redirect
            function ($request, Cart $cart, $e = null) {
                return view('larapress-ecommerce::redirect', [
                    'inputs' => [
                        'alert' => trans('larapress::ecommerce.messaging.purchase_failed'),
                        'type' => 'error',
                    ],
                    'url' => $cart->getFailedRedirect(),
                ]);
            },
            // already purchased cart
            function ($request, Cart $cart) {
                return view('larapress-ecommerce::redirect', [
                    'inputs' => [
                        'alert' => trans('larapress::ecommerce.messaging.purchase_success'),
                        'type' => 'success',
                    ],
                    'url' => $cart->getSuccessRedirect(),
                ]);
            },
        );
    }

    /**
     * Undocumented function
     *
     * @param IPurchaseService $service
     * @param Request $request
     * @param int $gateway_id
     * @param int $cart_id
     *
     * @return void
     */
    public function redirectToBankForCart(IBankingService $service, BankRedirectRequest $request)
    {
        return $service->redirectToBankForCart(
            $request,
            $request->getCartId(),
            function ($request, Cart $cart, $e = null) {
                return view('larapress-ecommerce::redirect', [
                    'inputs' => [
                        'alert' => trans('larapress::ecommerce.messaging.purchase_failed'),
                        'type' => 'error',
                    ],
                    'url' => $cart->getFailedRedirect(),
                ]);
            },
            function ($request, Cart $cart) {
                return view('larapress-ecommerce::redirect', [
                    'inputs' => [
                        'alert' => trans('larapress::ecommerce.messaging.purchase_success'),
                        'type' => 'success',
                    ],
                    'url' => $cart->getSuccessRedirect(),
                ]);
            },
        );
    }

    /**
     * Undocumented function
     *
     * @param IBankingService $service
     * @param Request $request
     * @param string $gatewayName
     * @param int $transaction_id
     * @return void
     */
    public function callbackFromBank(IBankingService $service, Request $request, $transaction_id)
    {
        return $service->verifyBankRequest(
            $request,
            $transaction_id,
            function ($request, Cart $cart, BankGatewayTransaction $transaction) {
                return view('larapress-ecommerce::redirect', [
                    'inputs' => [
                        'alert' => trans('larapress::ecommerce.messaging.purchase_success'),
                        'type' => 'success',
                    ],
                    'url' => $cart->getSuccessRedirect(),
                ]);
            },
            function ($request, Cart $cart, BankGatewayTransaction $transaction) {
                return view('larapress-ecommerce::redirect', [
                    'inputs' => [
                        'alert' => trans('larapress::ecommerce.messaging.purchase_success'),
                        'type' => 'success',
                    ],
                    'url' => $cart->getSuccessRedirect(),
                ]);
            },
            function ($request, Cart $cart, $e) {
                return view('larapress-ecommerce::redirect', [
                    'inputs' => [
                        'alert' => trans('larapress::ecommerce.messaging.purchase_failed'),
                        'type' => 'error',
                    ],
                    'url' => $cart->getFailedRedirect(),
                ]);
            },
            function ($request, Cart $cart, $e) {
                return view('larapress-ecommerce::redirect', [
                    'inputs' => [
                        'alert' => trans('larapress::ecommerce.messaging.purchase_canceled'),
                        'type' => 'warning',
                    ],
                    'url' => $cart->getCanceledRedirect(),
                ]);
            }
        );
    }
}
