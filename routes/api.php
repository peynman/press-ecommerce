<?php

use Illuminate\Support\Facades\Route;
use Larapress\ECommerce\Controllers\ProductReviewController;
use Larapress\ECommerce\Services\Cart\InstallmentCartController;
use Larapress\ECommerce\Services\Cart\PurchasingCartController;

// api routes with protected access
Route::middleware(config('larapress.crud.middlewares'))
    ->prefix(config('larapress.crud.prefix'))
    ->group(function () {
        PurchasingCartController::registerRoutes();
        InstallmentCartController::registerRoutes();
    });

// api routes with public access
Route::middleware(config('larapress.crud.public-middlewares'))
    ->prefix(config('larapress.crud.prefix'))
    ->group(function () {
        ProductReviewController::registerPublicApiRoutes();
    });
