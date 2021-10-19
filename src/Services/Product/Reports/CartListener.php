<?php

namespace Larapress\ECommerce\Services\Product\Reports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Cart\Base\CartProductPurchaseDetails;
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

        if (config('larapress.ecommerce.reports.gift_codes')) {
            if ($cart->status === Cart::STATUS_ACCESS_COMPLETE) {
                if ($cart->isPeriodicPaymentCart()) {
                    $products = $cart->products;

                    foreach ($products as $product) {
                        /** @var CartProductPurchaseDetails */
                        $details = new CartProductPurchaseDetails($product->pivot->data ?? []);

                        if ($details->virtualSale > 0) {
                            $this->metrics->updateMeasurement(
                                $cart->domain_id,
                                $cart->customer_id,
                                $product->id,
                                $cart->customer->getMembershipGroupIds(),
                                config('larapress.ecommerce.reports.products'),
                                config('larapress.ecommerce.reports.group'),
                                'carts.'.$cart->id.'.period.'.$product->id.'.virtual',
                                $details->virtualSale,
                                [
                                    'cart' => $cart->getPeriodicPaymentOriginalCartID(),
                                    'periodic_cart' => $cart->id,
                                    'period' => true,
                                    'type' => 'virtual'
                                ],
                                $event->timestamp
                            );
                        }

                        if ($details->realSale > 0) {
                            $this->metrics->updateMeasurement(
                                $cart->domain_id,
                                $cart->customer_id,
                                $product->id,
                                $cart->customer->getMembershipGroupIds(),
                                config('larapress.ecommerce.reports.products'),
                                config('larapress.ecommerce.reports.group'),
                                'carts.'.$cart->id.'.period.'.$product->id.'.real',
                                $details->realSale,
                                [
                                    'cart' => $cart->getPeriodicPaymentOriginalCartID(),
                                    'periodic_cart' => $cart->id,
                                    'period' => true,
                                    'type' => 'real'
                                ],
                                $event->timestamp
                            );
                        }

                        // paid amount from remaining sales
                        $this->metrics->updateMeasurement(
                            $cart->domain_id,
                            $cart->customer_id,
                            $product->id,
                            $cart->customer->getMembershipGroupIds(),
                            config('larapress.ecommerce.reports.products'),
                            config('larapress.ecommerce.reports.group'),
                            'carts.'.$cart->id.'.purchased.'.$product->id.'.remaining',
                            $details->currencyPaid,
                            [
                                'cart' => $cart->getPeriodicPaymentOriginalCartID(),
                                'periodic_cart' => $cart->id,
                                'period' => true,
                                'type' => 'remaining'
                            ],
                            $event->timestamp
                        );
                    }
                } else {
                    $products = $cart->products;

                    foreach ($products as $product) {
                        /** @var CartProductPurchaseDetails */
                        $details = new CartProductPurchaseDetails($product->pivot->data ?? []);

                        // virtual sales on purchase
                        if ($details->virtualSale > 0) {
                            $this->metrics->updateMeasurement(
                                $cart->domain_id,
                                $cart->customer_id,
                                $product->id,
                                $cart->customer->getMembershipGroupIds(),
                                config('larapress.ecommerce.reports.products'),
                                config('larapress.ecommerce.reports.group'),
                                'carts.'.$cart->id.'.purchased.'.$product->id.'.virtual',
                                $details->virtualSale,
                                [
                                    'cart' => $cart->id,
                                    'period' => false,
                                    'type' => 'virtual'
                                ],
                                $event->timestamp
                            );
                        }

                        // real sales on purchase
                        if ($details->realSale > 0) {
                            $this->metrics->updateMeasurement(
                                $cart->domain_id,
                                $cart->customer_id,
                                $product->id,
                                $cart->customer->getMembershipGroupIds(),
                                config('larapress.ecommerce.reports.products'),
                                config('larapress.ecommerce.reports.group'),
                                'carts.'.$cart->id.'.purchased.'.$product->id.'.real',
                                $details->realSale,
                                [
                                    'cart' => $cart->id,
                                    'period' => false,
                                    'type' => 'real'
                                ],
                                $event->timestamp
                            );
                        }

                        // total sales on purchase
                        if ($details->goodsSale > 0) {
                            $this->metrics->updateMeasurement(
                                $cart->domain_id,
                                $cart->customer_id,
                                $product->id,
                                $cart->customer->getMembershipGroupIds(),
                                config('larapress.ecommerce.reports.products'),
                                config('larapress.ecommerce.reports.group'),
                                'carts.'.$cart->id.'.purchased.'.$product->id.'.goods',
                                $details->goodsSale,
                                [
                                    'cart' => $cart->id,
                                    'period' => false,
                                    'type' => 'goods'
                                ],
                                $event->timestamp
                            );
                        }

                        // periods remaining unpaid on purchase
                        if ($details->periodsTotalPayment > 0) {
                            $this->metrics->updateMeasurement(
                                $cart->domain_id,
                                $cart->customer_id,
                                $product->id,
                                $cart->customer->getMembershipGroupIds(),
                                config('larapress.ecommerce.reports.products'),
                                config('larapress.ecommerce.reports.group'),
                                'carts.'.$cart->id.'.purchased.'.$product->id.'.remaining',
                                $details->periodsTotalPayment * -1,
                                [
                                    'cart' => $cart->id,
                                    'period' => false,
                                    'type' => 'remaining'
                                ],
                                $event->timestamp
                            );
                        }
                    }
                }
            }
        }
    }
}
