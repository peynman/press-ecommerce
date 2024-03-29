<?php

namespace Larapress\ECommerce\Services\Cart;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\Services\CRUD\CRUDController;
use Larapress\ECommerce\Services\Cart\Requests\CartGiftCodeRequest;
use Larapress\ECommerce\Services\Cart\IPurchasingCartService;
use Larapress\ECommerce\Services\GiftCodes\IGiftCodeService;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Services\Cart\Requests\CartContentModifyRequest;
use Larapress\ECommerce\Services\Cart\Requests\CartUpdateRequest;
use Larapress\ECommerce\Services\Cart\Requests\CartValidateRequest;

/**
 * Standard CRUD Controller for Cart Resource
 *
 * @group Customer Cart
 */
class PurchasingCartController extends CRUDController
{
    public static function registerRoutes()
    {
        Route::post(
            '/me/current-cart/update',
            '\\' . self::class . '@updatePurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.update');

        Route::post(
            '/me/current-cart/delivery',
            '\\' . self::class . '@updatePurchasingCartDelivery'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.delivery');

        Route::post(
            '/me/current-cart/add',
            '\\' . self::class . '@addToPurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.add');

        Route::post(
            '/me/current-cart/remove',
            '\\' . self::class . '@removeFromPurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.remove');

        Route::post(
            '/me/current-cart/apply/gift-code',
            '\\' . self::class . '@applyPurchasingCartGiftCode'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.purchasing.gift-code');

        Route::post(
            '/me/current-cart/validate',
            '\\' . self::class . '@validateCart'
        )->name(config('larapress.ecommerce.routes.carts.name') . '.any.validate');
    }

    /**
     * Apply Gift Code
     *
     * Apply a gift code to current users purchasing cart
     *
     * @return Response
     */
    public function applyPurchasingCartGiftCode(IPurchasingCartService $service, CartGiftCodeRequest $request)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        return $service->updateCartGiftCodeData(
            $request->getGiftCode(),
            $user,
            $request->getCurrency()
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
     * Undocumented function
     *
     * @param IPurchasingCartService $service
     * @param CartValidateRequest $request
     *
     * @return Response
     */
    public function validateCart(IPurchasingCartService $service, CartValidateRequest $request)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        return $service->validateCartBeforeForwardingToBank($request, $user, $request->getCurrency());
    }

    /**
     * Update Purchasing Cart
     *
     * @return Response
     */
    public function updatePurchasingCartDelivery(IPurchasingCartService $service, CartUpdateRequest $request)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        return $service->updateCartDeliveryData($request, $user, $request->getCurrency());
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
}
