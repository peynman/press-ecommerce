<?php

namespace Larapress\ECommerce\Providers;

class PackageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../migrations');

        $this->publishes(
            [
            __DIR__.'/../../config/ecommerce.php' => config_path('larapress/ecommerce.php'),
            ],
            ['config', 'larapress', 'larapress-ecommerce']
        );
    }
}
