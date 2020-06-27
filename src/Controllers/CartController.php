<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\Services\CartModifyRequest;
use Larapress\ECommerce\Services\CartUpdateRequest;
use Larapress\ECommerce\Services\IBankingService;

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
            '/me/current-cart/add',
            '\\'.self::class.'@addToPurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name').'.any.purchasing.add');

        Route::post(
            '/me/current-cart/remove',
            '\\'.self::class.'@removeFromPurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name').'.any.purchasing.remove');

        Route::post(
            '/me/current-cart/update',
            '\\'.self::class.'@updatePurchasingCart'
        )->name(config('larapress.ecommerce.routes.carts.name').'.any.purchasing.update');
    }



    /**
     * Undocumented function
     *
     * @param Request $request
     * @return Response
     */
    public function updatePurchasingCart(IBankingService $service, CartUpdateRequest $request) {
        return $service->updatePurchasingCart($request, $request->getCurrency());
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
}
