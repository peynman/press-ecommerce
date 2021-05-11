<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\Services\CRUD\BaseCRUDController;
use Larapress\ECommerce\CRUD\ProductReviewCRUDProvider;


/**
 * Standard CRUD Controller for Product Review resource.
 *
 * @group Product Review Management
 */
class ProductReviewController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.product_reviews.name'),
            self::class,
            ProductReviewCRUDProvider ::class
        );
    }
}
