<?php

use Illuminate\Support\Facades\Route;
use Larapress\ECommerce\Controllers\BankGatewayController;
use Larapress\ECommerce\Controllers\FileUploadController;
use Larapress\ECommerce\Controllers\ProductController;
use Larapress\ECommerce\Services\AdobeConnect\AdobeConnectController;
use Larapress\ECommerce\Services\CourseSession\CourseSessionFormController;
use Larapress\ECommerce\Services\SupportGroup\SupportGroupController;
use Larapress\Profiles\CRUDControllers\FormEntryController;

Route::middleware(config('larapress.crud.middlewares'))
    ->prefix(config('larapress.crud.prefix'))
    ->group(function() {
        AdobeConnectController::registerRoutes();
        SupportGroupController::registerRoutes();
    });


// api routes with public access
Route::middleware(config('larapress.pages.middleware'))
    ->prefix('api')
    ->group(function() {
        ProductController::registerPublicApiRoutes();
        FormEntryController::registerPublicApiRoutes();
        CourseSessionFormController::registerPublicApiRoutes();
    });

Route::middleware(config('larapress.pages.middleware'))
->prefix(config('larapress.pages.prefix'))
->group(function () {
    BankGatewayController::registerPublicWebRoutes();
    FileUploadController::registerWebRoutes();
});
