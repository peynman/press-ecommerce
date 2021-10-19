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
            'Larapress\ECommerce\Services\Banking\Reports\GatewayTransactionListener',
        ],

        // cart and product reports
        'Larapress\ECommerce\Services\Cart\CartEvent' => [
            'Larapress\ECommerce\Services\Cart\Reports\CartListener',
            'Larapress\ECommerce\Services\GiftCodes\Reports\CartListener',
            'Larapress\ECommerce\Services\Product\Reports\CartListener',
        ],

        'Larapress\ECommerce\Services\Banking\Events\WalletTransactionEvent' => [
            'Larapress\ECommerce\Services\Banking\Reports\WalletTransactionListener'
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
