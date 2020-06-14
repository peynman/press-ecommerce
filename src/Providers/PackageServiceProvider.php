<?php

namespace Larapress\ECommerce\Providers;

use Illuminate\Support\ServiceProvider;
use Larapress\ECommerce\Repositories\IProductRepository;
use Larapress\ECommerce\Repositories\ProductRepository;

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
