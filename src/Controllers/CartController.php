<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\Services\Banking\CartGiftCodeRequest;
use Larapress\ECommerce\Services\Banking\CartModifyRequest;
use Larapress\ECommerce\Services\Banking\CartUpdateRequest;
use Larapress\ECommerce\Services\Banking\IBankingService;

class CartController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.carts.name'),
            self::class,
            CartCRUDProvider::class
        );

        Route::post(
            '/me/all/installments',
            '\\'.self::class.'@getAllInstallments'
        )->name(config('larapress.ecommerce.routes.carts.name').'.any.purchasing.installments.all');

        Route::post(
            '/me/{cart_id}/installments/{product_id}',
            '\\'.self::class.'@getCartInstallments'
        )->name(config('larapress.ecommerce.routes.carts.name').'.any.purchasing.installments');

        Route::post(
            '/me/{cart_id}/update',
            '\\'.self::class.'@updatePurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name').'.any.purchasing.update');

        Route::post(
            '/me/current-cart/add',
            '\\'.self::class.'@addToPurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name').'.any.purchasing.add');

        Route::post(
            '/me/current-cart/remove',
            '\\'.self::class.'@removeFromPurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name').'.any.purchasing.remove');

        Route::post(
            '/me/current-cart/apply/gift-code',
            '\\'.self::class.'@checkPurchasingCartGiftCode'
        )->name(config('larapress.ecommerce.routes.carts.name').'.any.purchasing.gift-code');

    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $cart_id
     * @return void
     */
    public function checkPurchasingCartGiftCode(IBankingService $service, CartGiftCodeRequest $request) {
        return $service->checkGiftCodeForPurchasingCart($request, $request->getCurrency(), $request->getGiftCode());
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return Response
     */
    public function updatePurchasingCart(IBankingService $service, CartUpdateRequest $request, $cart_id) {
        return $service->updatePurchasingCart($request, $request->getCurrency(), $cart_id);
    }


    /**
     * Undocumented function
     *
     * @param Request $request
     * @return Response
     */
    public function addToPurchasingCart(IBankingService $service, CartModifyRequest $request) {
        return $service->addItemToPurchasingCart($request, $request->getProduct());
    }


    /**
     * Undocumented function
     *
     * @param Request $request
     * @return Response
     */
    public function removeFromPurchasingCart(IBankingService $service, CartModifyRequest $request) {
        return $service->removeItemFromPurchasingCart($request, $request->getProduct());
    }


    /**
     * Undocumented function
     *
     * @param IBankingService $service
     * @param int $cart_id
     * @param int $product_id
     * @return Response
     */
    public function getCartInstallments(IBankingService $service, $cart_id, $product_id) {
        return $service->getInstallmentsForProductInCart(Auth::user(), $cart_id, $product_id);
    }


    /**
     * Undocumented function
     *
     * @param IBankingService $service
     * @param int $cart_id
     * @param int $product_id
     * @return Response
     */
    public function getAllInstallments(IBankingService $service) {

    }
}
