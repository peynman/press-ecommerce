<?php

use Illuminate\Support\Facades\Route;
use Larapress\ECommerce\Controllers\BankGatewayController;


Route::middleware(config('larapress.crud.public-middlewares'))
    ->prefix(config('larapress.pages.prefix'))
    ->group(function () {
        BankGatewayController::registerPublicWebRoutes();
    });
