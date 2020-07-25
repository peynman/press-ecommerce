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
            // send general reports
            'Larapress\ECommerce\Services\Banking\Reports\CartPurchasedReport',
            // sync adobe connect servers
            'Larapress\ECommerce\Services\AdobeConnect\SyncACMeetingOnCartPurchase',
        ],
        'Larapress\ECommerce\Services\Banking\Events\WalletTransactionEvent' => [
            'Larapress\ECommerce\Services\Banking\Reports\WalletTransactionReport'
        ],

        // sync adobe connect servers on
        'Larapress\CRUD\Events\CRUDUpdated' => [
            'Larapress\ECommerce\Services\AdobeConnect\SyncACMeetingOnProductEvent',
        ],
        'Larapress\CRUD\Events\CRUDCreated' => [
            'Larapress\ECommerce\Services\AdobeConnect\SyncACMeetingOnProductEvent',
        ]
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
