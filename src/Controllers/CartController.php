<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\CartCRUDProvider;

class CartController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.carts.name'),
            self::class,
            CartCRUDProvider::class
        );
    }
}
