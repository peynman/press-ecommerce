<?php

namespace Larapress\ECommerce\Services\Product\Reports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\CRUD\Services\CRUD\ICRUDReportSource;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Cart\Base\CartInstallmentPurchaseDetails;
use Larapress\Reports\Services\Reports\ReportSourceTrait;
use Larapress\Reports\Services\Reports\IReportsService;
use Larapress\ECommerce\Services\Cart\Base\CartProductPurchaseDetails;
use Larapress\ECommerce\Services\Cart\CartPurchasedEvent;
use Larapress\Reports\Services\Reports\IMetricsService;
use Larapress\Reports\Services\Reports\MetricsSourceProperties;

class ProductPurchasedSalesReport implements ICRUDReportSource, ShouldQueue
{
    const MEASUREMENT_TYPE = 'product_sales';

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
        'type' => 4,
    ];

    public function __construct()
    {
        /** @var IReportsService */
        $this->reports = app(IReportsService::class);
        /** @var IMetricsService */
        $this->metrics = app(IMetricsService::class);

        $this->avReports = [
            'metrics.total.sales' => function ($user, array $options) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options);
                return $this->metrics->queryMeasurement(
                    '^products\.[0-9]\.sales\.[0-9]*\.amount$',
                    self::MEASUREMENT_TYPE,
                    null,
                    $props->filters,
                    $props->groups,
                    $props->domains,
                    $props->from,
                    $props->to
                );
            },
            'metrics.windowed.sales' => function ($user, array $options) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options);
                return $this->metrics->aggregateMeasurement(
                    '^products\.[0-9]\.sales\.[0-9]*\.amount$',
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
            'metrics.windowed.goods' => function ($user, array $options) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options);
                return $this->metrics->aggregateMeasurement(
                    '^products\.[0-9]\.goods$',
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

                if ($saleDetails->realSale > 0) {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        self::MEASUREMENT_TYPE,
                        'cart:' . $cart->id,
                        'products.' . $product->id . '.sales.' . WalletTransaction::TYPE_REAL_MONEY . '.amount',
                        $saleDetails->realSale,
                        $event->timestamp
                    );
                }
                if ($saleDetails->virtualSale > 0) {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        self::MEASUREMENT_TYPE,
                        'cart:' . $cart->id,
                        'products.' . $product->id . '.sales.' . WalletTransaction::TYPE_VIRTUAL_MONEY . '.amount',
                        $saleDetails->virtualSale,
                        $event->timestamp
                    );
                }
                if ($saleDetails->goodsSale > 0) {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        self::MEASUREMENT_TYPE,
                        'cart:' . $cart->id,
                        'products.' . $product->id . '.goods',
                        $saleDetails->goodsSale,
                        $event->timestamp
                    );
                }
                if ($saleDetails->periodsTotalPayment > 0) {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        self::MEASUREMENT_TYPE,
                        'cart:' . $cart->id,
                        'products.' . $product->id . '.periods_remaining.amount',
                        $saleDetails->periodsTotalPayment,
                        $event->timestamp
                    );
                }
                if ($saleDetails->offAmount > 0) {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        self::MEASUREMENT_TYPE,
                        'cart:' . $cart->id,
                        'products.' . $product->id . '.off.amount',
                        $saleDetails->offAmount,
                        $event->timestamp
                    );
                }

                if ($saleDetails->hasPeriods) {
                    if ($saleDetails->realSale > 0) {
                        $this->metrics->pushMeasurement(
                            $cart->domain_id,
                            self::MEASUREMENT_TYPE,
                            'cart:' . $cart->id,
                            'products.' . $product->id . '.sales_periodic.' . WalletTransaction::TYPE_REAL_MONEY . '.amount',
                            $saleDetails->realSale,
                            $event->timestamp
                        );
                    }
                    if ($saleDetails->virtualSale > 0) {
                        $this->metrics->pushMeasurement(
                            $cart->domain_id,
                            self::MEASUREMENT_TYPE,
                            'cart:' . $cart->id,
                            'products.' . $product->id . '.sales_periodic.' . WalletTransaction::TYPE_VIRTUAL_MONEY . '.amount',
                            $saleDetails->virtualSale,
                            $event->timestamp
                        );
                    }
                } else {
                    if ($saleDetails->realSale > 0) {
                        $this->metrics->pushMeasurement(
                            $cart->domain_id,
                            self::MEASUREMENT_TYPE,
                            'cart:' . $cart->id,
                            'products.' . $product->id . '.sales_fixed.' . WalletTransaction::TYPE_REAL_MONEY . '.amount',
                            $saleDetails->realSale,
                            $event->timestamp
                        );
                    }
                    if ($saleDetails->virtualSale > 0) {
                        $this->metrics->pushMeasurement(
                            $cart->domain_id,
                            self::MEASUREMENT_TYPE,
                            'cart:' . $cart->id,
                            'products.' . $product->id . '.sales_fixed.' . WalletTransaction::TYPE_VIRTUAL_MONEY . '.amount',
                            $saleDetails->virtualSale,
                            $event->timestamp
                        );
                    }
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
                $installmentDetails = new CartInstallmentPurchaseDetails($product->pivot->data);
                if ($installmentDetails->realSale > 0) {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        self::MEASUREMENT_TYPE,
                        'cart:' . $cart->id,
                        'products.' . $product->id . '.sales.' . WalletTransaction::TYPE_REAL_MONEY . '.amount',
                        $installmentDetails->realSale,
                        $event->timestamp
                    );
                }
                if ($installmentDetails->virtualSale > 0) {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        self::MEASUREMENT_TYPE,
                        'cart:' . $cart->id,
                        'products.' . $product->id . '.sales.' . WalletTransaction::TYPE_VIRTUAL_MONEY . '.amount',
                        $installmentDetails->virtualSale,
                        $event->timestamp
                    );
                }
                if ($installmentDetails->amount > 0) {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        self::MEASUREMENT_TYPE,
                        'cart:' . $cart->id,
                        'products.' . $product->id . '.periods_remaining.amount',
                        -1 * $installmentDetails->amount,
                        $event->timestamp
                    );
                }
            }
        }
    }
}
