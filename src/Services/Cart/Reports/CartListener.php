<?php

namespace Larapress\ECommerce\Services\Cart\Reports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Cart\CartEvent;
use Larapress\Reports\Services\Reports\IMetricsService;

class CartListener implements ShouldQueue
{
    public function __construct(public IMetricsService $metrics)
    {
    }

    public function handle(CartEvent $event)
    {
        /** @var Cart */
        $cart = $event->getCart();

        if (config('larapress.ecommerce.reports.carts')) {
            if ($cart->isPeriodicPaymentCart()) {
                $this->metrics->updateMeasurement(
                    $cart->domain_id,
                    $cart->customer_id,
                    null,
                    $cart->customer->getMembershipGroupIds(),
                    config('larapress.ecommerce.reports.carts'),
                    config('larapress.ecommerce.reports.group'),
                    'carts.'.$cart->id,
                    $cart->amount,
                    [
                        'status' => $cart->status,
                    ],
                    $event->timestamp
                );
            } else {
                $this->metrics->updateMeasurement(
                    $cart->domain_id,
                    $cart->customer_id,
                    null,
                    $cart->customer->getMembershipGroupIds(),
                    config('larapress.ecommerce.reports.carts'),
                    config('larapress.ecommerce.reports.group'),
                    'carts.'.$cart->id,
                    $cart->amount,
                    [
                        'status' => $cart->status,
                        'products_count' => $cart->products->count(),
                        'periodics' => $cart->getPeriodicProductsCount(),
                    ],
                    $event->timestamp
                );
            }
        }
    }
}
