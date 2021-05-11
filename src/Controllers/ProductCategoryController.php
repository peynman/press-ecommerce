<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\Services\CRUD\BaseCRUDController;
use Larapress\ECommerce\CRUD\ProductCategoryCRUDProvider;

/**
 * Standard CRUD Controller for Product Category resource.
 *
 * @group Product Category Management
 */
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
