<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\GiftCodeCRUDProvider;
use Larapress\ECommerce\Services\Banking\IBankingService;

class GiftCodeController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.gift_codes.name'),
            self::class,
            GiftCodeCRUDProvider::class
        );
    }
}
