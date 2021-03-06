<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\Services\CRUD\BaseCRUDController;
use Larapress\ECommerce\Services\Cart\CartGiftCodeRequest;
use Larapress\ECommerce\Services\Cart\IPurchasingCartService;
use Larapress\ECommerce\Services\GiftCodes\IGiftCodeService;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Services\Cart\CartContentModifyRequest;
use Larapress\ECommerce\Services\Cart\CartUpdateRequest;
use Larapress\ECommerce\Services\Cart\IInstallmentCartService;

/**
 * Standard CRUD Controller for Cart Resource
 *
 * @group Customer Cart
 */
class CartPurchasingController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        Route::post(
            '/me/installments',
            '\\' . self::class . '@getUserInstallments'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.installments.all');

        Route::post(
            '/me/current-cart/update',
            '\\' . self::class . '@updatePurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.update');

        Route::post(
            '/me/current-cart/add',
            '\\' . self::class . '@addToPurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.add');

        Route::post(
            '/me/current-cart/remove',
            '\\' . self::class . '@removeFromPurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.remove');

        Route::post(
            '/me/current-cart/gift-code/apply',
            '\\' . self::class . '@checkPurchasingCartGiftCode'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.gift-code');
    }

    /**
     * Apply Gift Code
     *
     * Apply a gift code to current users purchasing cart
     *
     * @return Response
     */
    public function checkPurchasingCartGiftCode(IGiftCodeService $service, IPurchasingCartService $pService, CartGiftCodeRequest $request)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        return $service->getGiftUsageDetailsForCart(
            $user,
            $pService->getPurchasingCart($user, $request->getCurrency()),
            $request->getGiftCode(),
        );
    }

    /**
     * Update Purchasing Cart
     *
     * @return Response
     */
    public function updatePurchasingCart(IPurchasingCartService $service, CartUpdateRequest $request)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        return $service->updatePurchasingCart($request, $user, $request->getCurrency());
    }

    /**
     * Add item to Purchasing Cart
     *
     * @return Response
     */
    public function addToPurchasingCart(IPurchasingCartService $service, CartContentModifyRequest $request)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        return $service->addItemToPurchasingCart($request, $user, $request->getProduct(), $request->getCurrency());
    }


    /**
     * Remove item from Purchasing Cart
     *
     * @return Response
     */
    public function removeFromPurchasingCart(IPurchasingCartService $service, CartContentModifyRequest $request)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        return $service->removeItemFromPurchasingCart($request, $user, $request->getProduct(), $request->getCurrency());
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
}
