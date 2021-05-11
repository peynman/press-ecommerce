<?php

use Illuminate\Support\Facades\Route;
use Larapress\ECommerce\Controllers\BankGatewayController;


Route::middleware(config('larapress.pages.middleware'))
    ->prefix(config('larapress.pages.prefix'))
    ->group(function () {
        BankGatewayController::registerPublicWebRoutes();
    });
