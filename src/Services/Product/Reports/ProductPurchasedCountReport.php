<?php

namespace Larapress\ECommerce\Services\Product\Reports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\CRUD\Services\CRUD\ICRUDReportSource;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Cart\Base\CartProductPurchaseDetails;
use Larapress\Reports\Services\Reports\ReportSourceTrait;
use Larapress\Reports\Services\Reports\IReportsService;
use Larapress\ECommerce\Services\Cart\CartPurchasedEvent;
use Larapress\Reports\Services\Reports\IMetricsService;
use Larapress\Reports\Services\Reports\MetricsSourceProperties;

class ProductPurchasedCountReport implements ICRUDReportSource, ShouldQueue
{
    const MEASUREMENT_TYPE = 'product_sales_count';

    use ReportSourceTrait;

    /** @var IReportsService */
    private $reports;

    /** @var IMetricsService */
    private $metrics;

    /** @var array */
    private $avReports;

    // start dot groups from 1 position_1.position_2.position_3...
    private $metricsDotGroups = [
        'product' => 2,
    ];

    public function __construct()
    {
        /** @var IReportsService */
        $this->reports = app(IReportsService::class);
        /** @var IMetricsService */
        $this->metrics = app(IMetricsService::class);

        $this->avReports = [
            // fixed sales count
            'metrics.total.sales_fixed.count' => function ($user, array $options) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options, $this->metricsDotGroups);
                return $this->metrics->queryMeasurement(
                    '^products\.[0-9]\.sales_fixed$',
                    self::MEASUREMENT_TYPE,
                    null,
                    $props->filters,
                    $props->groups,
                    $props->domains,
                    $props->from,
                    $props->to
                );
            },
            'metrics.windowed.sales_fixed.count' => function ($user, array $options) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options, $this->metricsDotGroups);
                return $this->metrics->aggregateMeasurement(
                    '^products\.[0-9]\.sales_fixed$',
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

            // periodic sales count
            'metrics.total.sales_periodic.count' => function ($user, array $options) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options, $this->metricsDotGroups);
                return $this->metrics->queryMeasurement(
                    '^products\.[0-9]\.sales_periodic$',
                    self::MEASUREMENT_TYPE,
                    null,
                    $props->filters,
                    $props->groups,
                    $props->domains,
                    $props->from,
                    $props->to,
                );
            },
            'metrics.windowed.sales_periodic.count' => function ($user, array $options) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options, $this->metricsDotGroups);
                return $this->metrics->aggregateMeasurement(
                    '^products\.[0-9]\.sales_periodic$',
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

            // periods count
            'metrics.total.periods.count' => function ($user, array $options) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options, $this->metricsDotGroups);
                return $this->metrics->queryMeasurement(
                    '^products\.[0-9]\.periods$',
                    self::MEASUREMENT_TYPE,
                    null,
                    $props->filters,
                    $props->groups,
                    $props->domains,
                    $props->from,
                    $props->to
                );
            },
            'metrics.windowed.periods.count' => function ($user, array $options) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options, $this->metricsDotGroups);
                return $this->metrics->aggregateMeasurement(
                    '^products\.[0-9]\.periods$',
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

    /**
     * Undocumented function
     *
     * @param CartPurchasedEvent $event
     *
     * @return void
     */
    public function handle(CartPurchasedEvent $event)
    {
        /** @var Cart */
        $cart = Cart::with(['products'])->find($event->cartId);

        // only run on complete access
        if ($cart->status !== Cart::STATUS_ACCESS_COMPLETE) {
            return;
        }

        if ($cart->isPeriodicPaymentCart()) {
            $this->reportProductInstallmentPaymentCart($cart, $event);
        } else {
            $this->reportProductPurchaseCart($cart, $event);
        }
    }

    /**
     * Undocumented function
     *
     * @param Cart $cart
     *
     * @return void
     */
    protected function reportProductPurchaseCart(Cart $cart, CartPurchasedEvent $event)
    {
        if (config('larapress.reports.reports.metrics_table')) {
            $products = $cart->products;
            foreach ($products as $product) {
                $saleDetails = new CartProductPurchaseDetails($product->pivot->data);
                if ($saleDetails->hasPeriods) {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        self::MEASUREMENT_TYPE,
                        'cart:' . $cart->id,
                        'products.' . $product->id . '.sales_periodic',
                        1,
                        $event->timestamp
                    );

                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        self::MEASUREMENT_TYPE,
                        'cart:' . $cart->id,
                        'products.' . $product->id . '.periods',
                        $saleDetails->periodsCount,
                        $event->timestamp
                    );
                } else {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        self::MEASUREMENT_TYPE,
                        'cart:' . $cart->id,
                        'products.' . $product->id . '.sales_fixed',
                        1,
                        $event->timestamp
                    );
                }
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param Cart $cart
     *
     * @return void
     */
    protected function reportProductInstallmentPaymentCart(Cart $cart, CartPurchasedEvent $event)
    {
        if (config('larapress.reports.reports.metrics_table')) {
            $products = $cart->products;
            foreach ($products as $product) {
                $this->metrics->pushMeasurement(
                    $cart->domain_id,
                    self::MEASUREMENT_TYPE,
                    'cart:' . $cart->id,
                    'products.' . $product->id . '.periods',
                    -1,
                    $event->timestamp
                );
            }
        }
    }
}
