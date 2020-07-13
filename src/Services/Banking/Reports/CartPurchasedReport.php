<?php

namespace Larapress\ECommerce\Services\Banking\Reports;

use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Base\IReportSource;
use Larapress\ECommerce\Services\Banking\Events\CartPurchasedEvent;
use Larapress\Reports\Services\BaseReportSource;
use Larapress\Reports\Services\IReportsService;
use Larapress\ECommerce\Services\Banking\ICartItem;

class CartPurchasedReport implements IReportSource
{
    use BaseReportSource;

    /** @var IReportsService */
    private $reports;

    /** @var array */
    private $avReports;

    public function __construct(IReportsService $reports)
    {
        $this->reports = $reports;
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
        $tags = [
            'domain' => $event->domain->id,
            'currency' => $event->cart->currency,
        ];
        $this->reports->pushMeasurement('carts.purchased', 1, $tags, [
            'amount' => $event->cart->amount,
            'gift' => isset($event->cart->data['gift_code']['amount']) ? $event->cart->data['gift_code']['amount']: 0,
        ], $event->timestamp);

        /** @var ICartItem[] */
        $items = $event->cart->products;
        $periodicPurchases = $event->cart->data['periodic_product_ids'];
        foreach ($items as $item) {
            $periodic = in_array($item->id, $periodicPurchases);
            $itemTags = [
                'domain' => $event->domain->id,
                'currency' => $event->cart->currency,
                'product' => $item->id,
                'periodic' => $periodic,
            ];

            $this->reports->pushMeasurement('carts.purchased.items', 1, $itemTags, [
                'amount' => $periodic ? $item->pricePeriodic() : $item->price(),
            ], $event->timestamp);
        }
    }
}
