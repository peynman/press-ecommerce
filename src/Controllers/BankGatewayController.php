<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Banking\IBankingService;

/**
 * Standard CRUD Controller for Bank Gateway resource.
 *
 * @group Bank Gateway Management
 */
class BankGatewayController extends Controller
{
    public static function registerPublicWebRoutes()
    {
        Route::any(config('larapress.ecommerce.routes.bank_gateways.name') . '/{gateway_id}/redirect/increase/{amount}/currency/{currency}', '\\' . self::class . '@redirectToBankForIncreaseAmount')
            ->name(config('larapress.ecommerce.routes.bank_gateways.name') . '.any.redirect.increase');
        Route::any(config('larapress.ecommerce.routes.bank_gateways.name') . '/{gateway_id}/redirect/{cart_id}', '\\' . self::class . '@redirectToBankForCart')
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
    public function redirectToBankForIncreaseAmount(IBankingService $service, Request $request, $gateway_id, $amount, $currency)
    {
        return $service->redirectToBankForAmount(
            $request,
            $gateway_id,
            $amount,
            $currency,
            // failed to redirect
            function ($request, Cart $cart, $e = null) {
                return response()
                    ->redirectTo(isset($cart->data['return_to']) ? $cart->data['return_to'] : config('larapress.ecommerce.banking.redirect.failed'))
                    ->with([
                        'answer' => [
                            'message' => trans('larapress::ecommerce.messaging.purchase_failed'),
                            'type' => 'error',
                        ]
                    ]);
            },
            // already purchased cart
            function ($request, Cart $cart) {
                return response()
                    ->redirectTo(isset($cart->data['return_to']) ? $cart->data['return_to'] : config('larapress.ecommerce.banking.redirect.already'))
                    ->with([
                        'answer' => [
                            'message' => trans('larapress::ecommerce.messaging.purchase_success'),
                            'type' => 'success',
                        ]
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
     * @param int $gateway_id
     * @return void
     */
    public function redirectToBankForCart(IBankingService $service, Request $request, $gateway_id, $cart_id)
    {
        return $service->redirectToBankForCart(
            $request,
            $cart_id,
            $gateway_id,
            function ($request, Cart $cart, $e = null) {
                return response()
                    ->redirectTo(isset($cart->data['return_to']) ? $cart->data['return_to'] : config('larapress.ecommerce.banking.redirect.failed'))
                    ->with([
                        'answer' => [
                            'message' => trans('larapress::ecommerce.messaging.purchase_failed'),
                            'type' => 'error',
                        ]
                    ]);
            },
            function ($request, Cart $cart) {
                return response()
                    ->redirectTo(isset($cart->data['return_to']) ? $cart->data['return_to'] : config('larapress.ecommerce.banking.redirect.already'))
                    ->with([
                        'answer' => [
                            'message' => trans('larapress::ecommerce.messaging.purchase_success'),
                            'type' => 'success',
                        ]
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
                return response()
                    ->redirectTo(isset($cart->data['return_to']) ? $cart->data['return_to'] : config('larapress.ecommerce.banking.redirect.already'))
                    ->with([
                        'answer' => [
                            'message' => trans('larapress::ecommerce.messaging.purchase_success'),
                            'type' => 'success',
                        ]
                    ]);
            },
            function ($request, Cart $cart, BankGatewayTransaction $transaction) {
                return response()
                    ->redirectTo(isset($cart->data['return_to']) ? $cart->data['return_to'] : config('larapress.ecommerce.banking.redirect.success'))
                    ->with([
                        'answer' => [
                            'message' => trans('larapress::ecommerce.messaging.purchase_success'),
                            'type' => 'success',
                        ]
                    ]);
            },
            function ($request, Cart $cart, $e) {
                return response()
                    ->redirectTo(isset($cart->data['return_to']) ? $cart->data['return_to'] : config('larapress.ecommerce.banking.redirect.failed'))
                    ->with([
                        'answer' => [
                            'message' => trans('larapress::ecommerce.messaging.purchase_failed'),
                            'type' => 'error',
                        ]
                    ]);
            },
            function ($request, Cart $cart, $e) {
                return response()
                    ->redirectTo(isset($cart->data['return_to']) ? $cart->data['return_to'] : config('larapress.ecommerce.banking.redirect.canceled'))
                    ->with([
                        'answer' => [
                            'message' => trans('larapress::ecommerce.messaging.purchase_canceled'),
                            'type' => 'warning',
                        ]
                    ]);
            }
        );
    }
}
