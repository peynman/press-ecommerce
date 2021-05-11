<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\Services\CRUD\BaseCRUDController;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\Services\Product\IProductService;


/**
 * Standard CRUD Controller for Product resource.
 *
 * @group Product Management
 */
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

    public static function registerPublicApiRoutes()
    {
        Route::post(
            config('larapress.ecommerce.routes.products.name') . '/repository',
            '\\' . self::class . '@queryRepository'
        )->name(config('larapress.ecommerce.routes.products.name') . 'any.repository');
    }

    /**
     * Query Products Reportistory
     *
     * This is a global method to query on all available products for customer.
     * <aside class="notice">This is a public method.😕</aside>
     *
     * @return Response
     *
     * @unauthorized
     */
    public function queryRepository(IProductService $service, Request $request)
    {
        return $service->queryProductsFromRequest($request);
    }


    /**
     * Clone Product
     *
     * @return Response
     */
    public function duplicateProduct(IProductService $service, Request $request, $id)
    {
        return $service->duplicateProductForRequest($request, $id);
    }
}
