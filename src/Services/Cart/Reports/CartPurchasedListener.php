<?php

namespace Larapress\ECommerce\Services\Cart\Reports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Cart\CartPurchasedEvent;
use Larapress\Reports\Services\Reports\IMetricsService;

class CartPurchasedListener implements ShouldQueue
{
    public function __construct(public IMetricsService $metrics)
    {
    }

    public function handle(CartPurchasedEvent $event)
    {
        /** @var Cart */
        $cart = $event->getCart();

        // only run on complete access
        if ($cart->status !== Cart::STATUS_ACCESS_COMPLETE) {
            return;
        }

        if (config('larapress.ecommerce.reports.carts')) {

        }
    }
}
