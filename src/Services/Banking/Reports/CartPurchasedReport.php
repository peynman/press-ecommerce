<?php

namespace Larapress\ECommerce\Services\Banking\Reports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Larapress\CRUD\BaseFlags;
use Larapress\CRUD\Services\IReportSource;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Banking\Events\CartPurchasedEvent;
use Larapress\Reports\Services\BaseReportSource;
use Larapress\Reports\Services\IReportsService;
use Larapress\ECommerce\Services\Banking\ICartItem;
use Larapress\Reports\Services\IMetricsService;

class CartPurchasedReport implements IReportSource, ShouldQueue
{
    use BaseReportSource;

    /** @var IReportsService */
    private $reports;

    /** @var IMetricsService */
    private $metrics;

    /** @var array */
    private $avReports;

    public function __construct(IReportsService $reports, IMetricsService $metrics)
    {
        $this->reports = $reports;
        $this->metrics = $metrics;
        $this->avReports = [
            'carts.purchased.total' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                return $this->reports->queryMeasurement(
                    'carts.purchased',
                    $filters,
                    $groups,
                    array_merge(["_value"], $groups),
                    $fromC,
                    $toC,
                    'count()'
                );
            },
            'carts.purchased.windowed' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                $window = isset($options['window']) ? $options['window'] : '1h';
                return $this->reports->queryMeasurement(
                    'carts.purchased',
                    $filters,
                    $groups,
                    array_merge(["_value", "_time"], $groups),
                    $fromC,
                    $toC,
                    'aggregateWindow(every: '.$window.', fn: sum)'
                );
            }
        ];
    }

    public function handle(CartPurchasedEvent $event)
    {
        $supportProfileId = isset($event->cart->customer->supportProfile['id']) ? $event->cart->customer->supportProfile['id']: null;
        $tags = [
            'domain' => $event->cart->domain_id,
            'currency' => $event->cart->currency,
            'support' => $supportProfileId,
            'type' => $event->cart->status,
            'support' => $supportProfileId,
        ];
        $this->reports->pushMeasurement('carts.purchased', 1, $tags, [
            'amount' => $event->cart->amount,
            'gift' => isset($event->cart->data['gift_code']['amount']) ? $event->cart->data['gift_code']['amount']: 0,
        ], $event->timestamp);

        if (!is_null($supportProfileId)) {
            $this->metrics->pushMeasurement(
                $event->cart->domain_id,
                'carts.sales_amount.'.$supportProfileId,
                $event->cart->amount
            );
        }

        // add to original product sales if this is a peridic paument
        if (BaseFlags::isActive($event->cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART)) {
            $originalProductId = $event->cart->data['periodic_pay']['product']['id'];
            $paymentPeriodIndex = $event->cart->data['periodic_pay']['index'];
            $amount = floatval($event->cart->amount);
            $this->metrics->pushMeasurement(
                $event->cart->domain_id,
                'product.'.$originalProductId.'.sales_amount',
                $amount
            );
            if (!is_null($supportProfileId)) {
                $this->metrics->pushMeasurement(
                    $event->cart->domain_id,
                    'product.'.$originalProductId.'.sales_amount.'.$supportProfileId,
                    $amount
                );
            }

            $itemTags = [
                'domain' => $event->cart->domain_id,
                'currency' => $event->cart->currency,
                'product' => $originalProductId,
                'periodic' => true,
                'period' => $paymentPeriodIndex,
                'support' => $supportProfileId,
            ];

            $this->reports->pushMeasurement('carts.purchased.items', 1, $itemTags, [
                'amount' => $amount,
            ], $event->timestamp);
        }

        /** @var ICartItem[] */
        $items = $event->cart->products;
        $periodicPurchases = isset($event->cart->data['periodic_product_ids']) ? $event->cart->data['periodic_product_ids'] : [];
        if (!is_null($items) && count($items) > 0) {
            foreach ($items as $item) {
                $periodic = in_array($item->id, $periodicPurchases);
                $itemTags = [
                    'domain' => $event->cart->domain_id,
                    'currency' => $event->cart->currency,
                    'product' => $item->id,
                    'periodic' => $periodic,
                    'support' => $supportProfileId,
                ];

                $this->metrics->pushMeasurement(
                    $event->cart->domain_id,
                    'product.'.$item->id.'.sales_amount',
                    $periodic ? $item->pricePeriodic() : $item->price()
                );
                if (!is_null($supportProfileId)) {
                    $this->metrics->pushMeasurement(
                        $event->cart->domain_id,
                        'product.'.$item->id.'.sales_amount.'.$supportProfileId,
                        $periodic ? $item->pricePeriodic() : $item->price()
                    );
                }
                if ($periodic) {
                    $this->metrics->pushMeasurement(
                        $event->cart->domain_id,
                        'product.'.$item->id.'.sales_periodic',
                        1
                    );
                    if (!is_null($supportProfileId)) {
                        $this->metrics->pushMeasurement(
                            $event->cart->domain_id,
                            'product.'.$item->id.'.sales_periodic.'.$supportProfileId,
                            1
                        );
                    }
                } else {
                    $this->metrics->pushMeasurement(
                        $event->cart->domain_id,
                        'product.'.$item->id.'.sales_fixed',
                        1
                    );
                    if (!is_null($supportProfileId)) {
                        $this->metrics->pushMeasurement(
                            $event->cart->domain_id,
                            'product.'.$item->id.'.sales_fixed.'.$supportProfileId,
                            1
                        );
                    }
                }

                $this->reports->pushMeasurement('carts.purchased.items', 1, $itemTags, [
                    'amount' => $periodic ? $item->pricePeriodic() : $item->price(),
                ], $event->timestamp);
            }
        }
    }
}
