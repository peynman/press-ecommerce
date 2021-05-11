<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\Services\CRUD\BaseCRUDController;
use Larapress\ECommerce\CRUD\CartCRUDProvider;

/**
 * Standard CRUD Controller for Cart resource.
 *
 * @group Carts Management
 */
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
