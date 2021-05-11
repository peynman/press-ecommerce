<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\Services\CRUD\BaseCRUDController;
use Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider;

/**
 * Standard CRUD Controller for Bank Gateway Transaction resource.
 *
 * @group Bank Gateway Transaction Management
 */
class BankGatewayTransactionController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.bank_gateway_transactions.name'),
            self::class,
            BankGatewayTransactionCRUDProvider::class
        );
    }
}
