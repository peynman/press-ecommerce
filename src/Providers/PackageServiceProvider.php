<?php

namespace Larapress\ECommerce\Providers;

use Illuminate\Support\ServiceProvider;
use Larapress\ECommerce\Repositories\IProductRepository;
use Larapress\ECommerce\Repositories\ProductRepository;
use Larapress\ECommerce\Services\Banking\BankingService;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\ECommerce\Services\Product\IProductService;
use Larapress\ECommerce\Services\Product\ProductService;
use Larapress\ECommerce\Commands\UpdateInstallmentCarts;
use Larapress\ECommerce\Services\Cart\CartService;
use Larapress\ECommerce\Services\Cart\ICartService;
use Larapress\ECommerce\Services\Cart\IPurchasingCartService;
use Larapress\ECommerce\Services\Cart\PurchasingCartService;
use Larapress\ECommerce\Services\GiftCodes\GiftCodeService;
use Larapress\ECommerce\Services\GiftCodes\IGiftCodeService;
use Larapress\ECommerce\Services\Wallet\IWalletService;
use Larapress\ECommerce\Services\Wallet\WalletService;

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
        $this->app->bind(IProductService::class, ProductService::class);
        $this->app->bind(IBankingService::class, BankingService::class);
        $this->app->bind(ICartService::class, CartService::class);
        $this->app->bind(IPurchasingCartService::class, PurchasingCartService::class);
        $this->app->bind(IGiftCodeService::class, GiftCodeService::class);
        $this->app->bind(IWalletService::class, WalletService::class);

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
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
        $this->loadMigrationsFrom(__DIR__.'/../../migrations');

        $this->publishes(
            [
            __DIR__.'/../../config/ecommerce.php' => config_path('larapress/ecommerce.php'),
            ],
            ['config', 'larapress', 'larapress-ecommerce']
        );


        if ($this->app->runningInConsole()) {
            $this->commands([
                UpdateInstallmentCarts::class,
            ]);
        }
    }
}
