<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Repositories\IProductRepository;

class ProductController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.products.name'),
            self::class,
            ProductCRUDProvider::class
        );
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return void
     */
    public function queryRepository(Request $request) {
        /** @var IProductRepository */
        $repo = app(IProductRepository::class);
        return $repo->getProductsPaginated(
            Auth::user(),
            $request->get('page', 1),
            $request->get('limit', config('larapress.ecommerce.repository.per_page', 50)),
            $request->get('categories', []),
            $request->get('types', [])
        );
    }
}
