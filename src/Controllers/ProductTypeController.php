<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\ProductTypeCRUDProvider;

class ProductTypeController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.product_types.name'),
            self::class,
            ProductTypeCRUDProvider::class
        );
    }
}
