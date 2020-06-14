<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\WalletTransactionCRUDProvider;

class WalletTransactionController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.wallet_transactions.name'),
            self::class,
            WalletTransactionCRUDProvider::class
        );
    }
}
