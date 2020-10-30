<?php

namespace Larapress\ECommerce\Providers;

use Illuminate\Support\ServiceProvider;
use Larapress\ECommerce\Commands\ProductCommands;
use Larapress\ECommerce\Repositories\IProductRepository;
use Larapress\ECommerce\Repositories\ProductRepository;
use Larapress\ECommerce\Services\AdobeConnect\AdobeConnectCommands;
use Larapress\ECommerce\Services\AdobeConnect\AdobeConnectService;
use Larapress\ECommerce\Services\AdobeConnect\IAdobeConnectService;
use Larapress\ECommerce\Services\Azmoon\AzmoonService;
use Larapress\ECommerce\Services\Azmoon\IAzmoonService;
use Larapress\ECommerce\Services\Banking\BankingService;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\ECommerce\Services\CourseSession\ICourseSessionFormService;
use Larapress\ECommerce\Services\CourseSession\CourseSessionFormService;
use Larapress\ECommerce\Services\CourseSession\CourseSessionRepository;
use Larapress\ECommerce\Services\CourseSession\ICourseSessionRepository;
use Larapress\ECommerce\Services\FileUpload\FileUploadService;
use Larapress\Ecommerce\Services\FileUpload\IFileUploadService;
use Larapress\ECommerce\Services\LiveStream\ILiveStreamService;
use Larapress\ECommerce\Services\LiveStream\LiveStreamService;
use Larapress\ECommerce\Services\Product\IProductService;
use Larapress\ECommerce\Services\Product\ProductService;
use Larapress\ECommerce\Services\SupportGroup\ISupportGroupService;
use Larapress\ECommerce\Services\SupportGroup\SupportGroupService;
use Larapress\ECommerce\Services\VOD\IVODStreamService;
use Larapress\ECommerce\Services\VOD\VODStreamService;

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
        $this->app->bind(IProductService::class, ProductService::class);
        $this->app->bind(IVODStreamService::class, VODStreamService::class);
        $this->app->bind(ICourseSessionFormService::class, CourseSessionFormService::class);
        $this->app->bind(IAdobeConnectService::class, AdobeConnectService::class);
        $this->app->bind(ICourseSessionRepository::class, CourseSessionRepository::class);
        $this->app->bind(ISupportGroupService::class, SupportGroupService::class);
        $this->app->bind(IAzmoonService::class, AzmoonService::class);

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
        $this->loadMigrationsFrom(__DIR__.'/../../migrations');

        $this->publishes(
            [
            __DIR__.'/../../config/ecommerce.php' => config_path('larapress/ecommerce.php'),
            ],
            ['config', 'larapress', 'larapress-ecommerce']
        );


        if ($this->app->runningInConsole()) {
            $this->commands([
                ProductCommands::class,
                AdobeConnectCommands::class,
            ]);
        }
    }
}
