<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\BankGatewayCRUDProvider;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\IBankingService;

class BankGatewayController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.bank_gateways.name'),
            self::class,
            BankGatewayCRUDProvider::class
        );
    }

    public static function registerWebRoutes() {
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
    public function redirectToBankForIncreaseAmount(IBankingService $service, Request $request, $gateway_id, $amount, $currency) {
        return $service->redirectToBankForAmount(
            $request,
            $gateway_id,
            $amount,
            $currency,
			null
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
    public function redirectToBankForCart(IBankingService $service, Request $request, $gateway_id, $cart_id) {
		return $service->redirectToBankForCart(
			$request,
			$cart_id,
			$gateway_id,
			null
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
            function($request, Cart $cart, BankGatewayTransaction $transaction) {
                return response()->redirectTo(config('larapress.ecommerce.banking.redirect.already'));
			},
			function($request, Cart $cart, BankGatewayTransaction $transaction) {
                return response()->redirectTo(config('larapress.ecommerce.banking.redirect.success'));
			},
			function($request, Cart $cart, $e) {
                return response()->redirectTo(config('larapress.ecommerce.banking.redirect.failed'));
			}
        );
    }

}
