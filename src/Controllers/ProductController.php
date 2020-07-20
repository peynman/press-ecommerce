<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Repositories\IProductRepository;
use Larapress\ECommerce\Services\Product\IProductService;

class ProductController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.products.name'),
            self::class,
            ProductCRUDProvider::class,
            [
                'create.duplicate' => [
                    'methods' => ['POST'],
                    'uses' => '\\'.self::class.'@duplicateProduct',
                    'url' => config('larapress.ecommerce.routes.products.name').'/{id}/duplicate',
                ]
            ]
        );
    }

    public static function registerPublicApiRoutes() {
        Route::post(
            config('larapress.ecommerce.routes.products.name') . '/repository',
            '\\' . self::class . '@queryRepository'
        )->name(config('larapress.ecommerce.routes.products.name') . 'any.repository');
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return array
     */
    public function queryRepository(IProductService $service, Request $request) {
        return $service->queryProductsFromRequest($request);
    }


    /**
     * Undocumented function
     *
     * @param IProductService $service
     * @param Request $request
     * @param int $id
     * @return array
     */
    public function duplicateProduct(IProductService $service, Request $request, $id) {
        return $service->duplicateProductForRequest($request, $id);
    }
}
