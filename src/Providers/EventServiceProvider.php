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
        'Larapress\ECommerce\Services\Banking\Events\BankGatewayTransactionEvent' => [
            'Larapress\ECommerce\Services\Banking\Reports\BankGatewayTransactionReport',
        ],
        'Larapress\ECommerce\Services\Banking\Events\CartPurchasedEvent' => [
            'Larapress\ECommerce\Services\Banking\Reports\CartPurchasedReport',
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
