<?php

namespace Larapress\ECommerce\Services\Cart;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\Services\CRUD\CRUDController;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Services\Cart\IInstallmentCartService;
use Larapress\ECommerce\Services\Cart\Requests\CartInstallmentUpdateRequest;

/**
 * Standard CRUD Controller for Cart Resource
 *
 * @group Customer Cart
 */
class InstallmentCartController extends CRUDController
{
    public static function registerRoutes()
    {
        Route::get(
            '/me/installments/current-period',
            '\\' . self::class . '@getCurrentPeriodInstallments'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.installments.current-period');

        Route::post(
            '/me/installments/{cart_id}',
            '\\' . self::class . '@updateInstallmentCart'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.installments.update');

        Route::get(
            '/me/installments',
            '\\' . self::class . '@getUserInstallments'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.installments.all');
    }

    /**
     * Get Customer Installments
     *
     * @return Response
     */
    public function getUserInstallments(IInstallmentCartService $service)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        return $service->getUserInstallments($user);
    }

    /**
     * Make a cart with all installments for this period
     *
     * @return Response
     */
    public function getCurrentPeriodInstallments(IInstallmentCartService $service)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        return $service->updateSingleInstallmentsCarts($user);
    }

    /**
     * update installment cart
     *
     * @param IInstallmentCartService $service
     * @param CartInstallmentUpdateRequest $request
     *
     * @return Response
     */
    public function updateInstallmentCart(IInstallmentCartService $service, CartInstallmentUpdateRequest $request, $cartId)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        return $service->updatePeriodicPaymentCart($user, $cartId, $request);
    }
}
