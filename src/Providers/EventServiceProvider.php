<?php

namespace Larapress\ECommerce\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{

    /**
     * The event handler mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        // bank gateway transactions reports
        'Larapress\ECommerce\Services\Banking\BankGatewayTransactionEvent' => [
            'Larapress\ECommerce\Services\Banking\Reports\GatewayTransactionSendReport',
        ],

        // cart and product reports
        'Larapress\ECommerce\Services\Cart\CartPurchasedEvent' => [
            // send cart reports
            'Larapress\ECommerce\Services\Cart\CartPurchasedReport',
            // send product reports
            'Larapress\ECommerce\Services\Product\Reports\ProductPurchasedCountReport',
            'Larapress\ECommerce\Services\Product\Reports\ProductPurchasedSalesReport',
        ],

        'Larapress\ECommerce\Services\Banking\Events\WalletTransactionEvent' => [
            'Larapress\ECommerce\Services\Banking\Reports\WalletTransactionReport'
        ],
    ];


    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
