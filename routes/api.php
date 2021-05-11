<?php

use Illuminate\Support\Facades\Route;
use Larapress\ECommerce\Controllers\ProductController;

// api routes with public access
Route::middleware(config('larapress.pages.middleware'))
    ->prefix(config('larapress.crud.prefix'))
    ->group(function () {
        ProductController::registerPublicApiRoutes();
    });
