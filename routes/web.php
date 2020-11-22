<?php

use Illuminate\Support\Facades\Route;
use Larapress\Auth\Signin\SigninController;
use Larapress\ECommerce\Controllers\BankGatewayController;
use Larapress\ECommerce\Controllers\FileUploadController;
use Larapress\ECommerce\Controllers\ProductController;
use Larapress\ECommerce\Services\Azmoon\AzmoonController;
use Larapress\ECommerce\Services\CourseSession\CourseSessionFormController;
use Larapress\ECommerce\Services\LiveStream\LiveStreamController;
use Larapress\ECommerce\Services\PDF\PDFFileDownloadController;
use Larapress\ECommerce\Services\VOD\VODStreamController;
use Larapress\Pages\Controllers\PageRenderController;
use Larapress\Profiles\CRUDControllers\FormEntryController;

// api routes with public access
Route::middleware(config('larapress.pages.middleware'))
    ->prefix('api')
    ->group(function() {
        LiveStreamController::registerPublicApiRoutes();
        ProductController::registerPublicApiRoutes();
        FormEntryController::registerPublicApiRoutes();
        CourseSessionFormController::registerPublicApiRoutes();
        AzmoonController::registerPublicAPIRoutes();
    });

Route::middleware(config('larapress.pages.middleware'))
    ->prefix(config('larapress.pages.prefix'))
    ->group(function () {
        SigninController::registerPublicWebRoutes();
        BankGatewayController::registerPublicWebRoutes();
        VODStreamController::registerPublicWebRoutes();
        AzmoonController::registerPublicWebRoutes();

        PDFFileDownloadController::registerWebRoutes();
        FileUploadController::registerWebRoutes();

        // alwayes put PageRenderController at last
        PageRenderController::registerPublicWebRoutes();
        // do not register controllers down here ...
    });
