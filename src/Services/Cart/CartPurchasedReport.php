<?php

namespace Larapress\ECommerce\Services\Cart;

use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\CRUD\Services\CRUD\ICRUDReportSource;
use Larapress\ECommerce\Models\Cart;
use Larapress\Reports\Services\Reports\ReportSourceTrait;
use Larapress\Reports\Services\Reports\IReportsService;
use Larapress\Reports\Services\Reports\IMetricsService;
use Larapress\Reports\Services\Reports\MetricsSourceProperties;
use Larapress\Reports\Services\Reports\ReportSourceProperties;

class CartPurchasedReport implements ICRUDReportSource, ShouldQueue
{
    const MEASUREMENT_TYPE = 'cart';

    use ReportSourceTrait;

    /** @var IReportsService */
    private $reports;

    /** @var IMetricsService */
    private $metrics;

    /** @var array */
    private $avReports;

    // start dot groups from 1 position_1.position_2.position_3...
    private $metricsDotGroups = [
        'status' => 2,
    ];

    public function __construct()
    {
        /** @var IReportsService */
        $this->reports = app(IReportsService::class);
        /** @var IMetricsService */
        $this->metrics = app(IMetricsService::class);

        $this->avReports = [
            'reports.total' => function ($user, array $options = []) {
                $props = ReportSourceProperties::fromReportSourceOptions($user, $options);
                return $this->reports->queryMeasurement(
                    'carts.purchased',
                    $props->filters,
                    $props->groups,
                    array_merge(["_value"], $props->groups),
                    $props->from,
                    $props->to,
                    'count()'
                );
            },
            'reports.windowed' => function ($user, array $options = []) {
                $props = ReportSourceProperties::fromReportSourceOptions($user, $options);
                return $this->reports->queryMeasurement(
                    'carts.purchased',
                    $props->filters,
                    $props->groups,
                    array_merge(["_value", "_time"], $props->groups),
                    $props->from,
                    $props->to,
                    'aggregateWindow(every: ' . $props->window . ', fn: sum)'
                );
            },
            'metrics.total' => function ($user, array $options = []) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options, $this->metricsDotGroups);
                return $this->metrics->queryMeasurement(
                    '^carts\.[0-9]*$',
                    self::MEASUREMENT_TYPE,
                    null,
                    $props->filters,
                    $props->groups,
                    $props->domains,
                    $props->from,
                    $props->to
                );
            },
            'metrics.windowed' => function ($user, array $options = []) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options, $this->metricsDotGroups);
                return $this->metrics->aggregateMeasurement(
                    '^carts\.[0-9]*$',
                    self::MEASUREMENT_TYPE,
                    null,
                    $props->filters,
                    $props->groups,
                    $props->domains,
                    $props->from,
                    $props->to,
                    $props->window
                );
            },
        ];
    }

    public function handle(CartPurchasedEvent $event)
    {
        /** @var Cart */
        $cart = $event->getCart();

        // only run on complete access
        if ($cart->status !== Cart::STATUS_ACCESS_COMPLETE) {
            return;
        }

        if (config('larapress.reports.reports.reports_service')) {
            $tags = [
                'domain' => $cart->domain_id,
                'currency' => $cart->currency,
                'cart' => $cart->id,
            ];
            $this->reports->pushMeasurement('carts.purchased', $cart->amount, $tags, [], $event->timestamp);
        }

        if (config('larapress.reports.reports.metrics_table')) {
            $this->metrics->pushMeasurement(
                $cart->domain_id,
                self::MEASUREMENT_TYPE,
                'cart:'.$cart->id,
                'carts.'.$cart->status,
                $cart->amount,
                $event->timestamp
            );
        }
    }
}
