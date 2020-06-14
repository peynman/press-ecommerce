<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;

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
}
