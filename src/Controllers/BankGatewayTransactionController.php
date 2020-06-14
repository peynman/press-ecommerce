<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\BankGatewayTransactionCRUDProvider;

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
