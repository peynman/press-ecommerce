<?php

namespace Larapress\ECommerce\Providers;

use Illuminate\Support\ServiceProvider;
use Larapress\ECommerce\Repositories\IProductRepository;
use Larapress\ECommerce\Repositories\ProductRepository;
use Larapress\ECommerce\Services\Banking\BankingService;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\ECommerce\Services\FileUpload\FileUploadService;
use Larapress\Ecommerce\Services\FileUpload\IFileUploadService;
use Larapress\ECommerce\Services\LiveStream\ILiveStreamService;
use Larapress\ECommerce\Services\LiveStream\LiveStreamService;

class PackageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(IProductRepository::class, ProductRepository::class);
        $this->app->bind(IBankingService::class, BankingService::class);
        $this->app->bind(ILiveStreamService::class, LiveStreamService::class);
        $this->app->bind(IFileUploadService::class, FileUploadService::class);

        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'larapress');
        $this->loadMigrationsFrom(__DIR__.'/../../migrations');

        $this->publishes(
            [
            __DIR__.'/../../config/ecommerce.php' => config_path('larapress/ecommerce.php'),
            ],
            ['config', 'larapress', 'larapress-ecommerce']
        );
    }
}
