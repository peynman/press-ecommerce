<?php

namespace Larapress\ECommerce\Providers;

use Illuminate\Support\ServiceProvider;
use Larapress\ECommerce\Services\Banking\BankingService;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\ECommerce\Services\Product\IProductService;
use Larapress\ECommerce\Services\Product\ProductService;
use Larapress\ECommerce\Commands\UpdateInstallmentCarts;
use Larapress\ECommerce\Services\Banking\BankGatewayRepository;
use Larapress\ECommerce\Services\Banking\IBankGatewayRepository;
use Larapress\ECommerce\Services\Cart\CartRepository;
use Larapress\ECommerce\Services\Cart\CartService;
use Larapress\ECommerce\Services\Cart\DeliveryAgent\DeliveryAgent;
use Larapress\ECommerce\Services\Cart\DeliveryAgent\IDeliveryAgent;
use Larapress\ECommerce\Services\Cart\ICartRepository;
use Larapress\ECommerce\Services\Cart\ICartService;
use Larapress\ECommerce\Services\Cart\IInstallmentCartService;
use Larapress\ECommerce\Services\Cart\InstallmentCartService;
use Larapress\ECommerce\Services\Cart\IPurchasingCartService;
use Larapress\ECommerce\Services\Cart\PurchasingCartService;
use Larapress\ECommerce\Services\GiftCodes\GiftCodeService;
use Larapress\ECommerce\Services\GiftCodes\IGiftCodeService;
use Larapress\ECommerce\Services\Product\IProductRepository;
use Larapress\ECommerce\Services\Product\IProductReviewService;
use Larapress\ECommerce\Services\Product\ProductRepository;
use Larapress\ECommerce\Services\Product\ProductReviewService;
use Larapress\ECommerce\Services\Wallet\IWalletService;
use Larapress\ECommerce\Services\Wallet\IWalletTransactionRepository;
use Larapress\ECommerce\Services\Wallet\WalletService;
use Larapress\ECommerce\Services\Wallet\WalletTransactionRepository;

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
        $this->app->bind(IInstallmentCartService::class, InstallmentCartService::class);
        $this->app->bind(IProductReviewService::class, ProductReviewService::class);
        $this->app->bind(ICartRepository::class, CartRepository::class);
        $this->app->bind(IWalletTransactionRepository::class, WalletTransactionRepository::class);
        $this->app->bind(IBankGatewayRepository::class, BankGatewayRepository::class);
        $this->app->bind(IDeliveryAgent::class, DeliveryAgent::class);

        $this->app->register(EventServiceProvider::class);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'larapress-ecommerce');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'larapress');
        $this->loadMigrationsFrom(__DIR__.'/../../migrations');
        $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

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
