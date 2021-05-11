<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\Services\CRUD\BaseCRUDController;
use Larapress\ECommerce\CRUD\ProductTypeCRUDProvider;


/**
 * Standard CRUD Controller for Product Type resource.
 *
 * @group Product Type Management
 */
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
