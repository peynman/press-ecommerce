<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\ProductCategoryCRUDProvider;

class ProductCategoryController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.product_categories.name'),
            self::class,
            ProductCategoryCRUDProvider::class
        );
    }
}
