<?php

namespace Larapress\ECommerce\Services\Banking\Reports;

use Carbon\Carbon;
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
                    'aggregateWindow(every: ' . $window . ', fn: sum)'
                );
            }
        ];
    }

    public function handle(CartPurchasedEvent $event)
    {
        /** @var Cart */
        $cart = Cart::with([
            'customer',
            'products',
        ])->find($event->cartId);

        // just run on purchased carts
        if ($cart->status === Cart::STATUS_UNVERIFIED) {
            return;
        }

        $supportProfileId = $cart->customer->getSupportUserId();
        $supportProfileTimestamp = $cart->customer->getSupportUserStartedDate();

        $tags = [
            'domain' => $cart->domain_id,
            'currency' => $cart->currency,
            'support' => $supportProfileId,
            'cart' => $cart->id,
        ];
        $this->reports->pushMeasurement('carts.purchased', 1, $tags, [
            'type' => $cart->status,
            'amount' => floatval($cart->amount),
        ], $event->timestamp);


        $purchaseTimestamp = isset($cart->data['period_start']) ? Carbon::parse($cart->data['period_start']) : $cart->updated_at;

        // add to product sales count if this is not a period payment
        //   since this is counting the sales we dont include FLAGS_PERIOD_PAYMENT_CART and count them separately
        /** @var ICartItem[] */
        $items = $cart->products;
        if (!is_null($items) && count($items) > 0) {
            // add each product sales counter
            if (
                !BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART) &&
                BaseFlags::isActive($cart->flags, Cart::FLAGS_USER_CART)
            ) {
                // periodic purchase for product
                $periodicPurchases = isset($cart->data['periodic_product_ids']) ? $cart->data['periodic_product_ids'] : [];
                foreach ($items as $item) {
                    if (in_array($item->id, $periodicPurchases)) {
                        $this->metrics->pushMeasurement(
                            $cart->domain_id,
                            'cart:' . $cart->id,
                            'product.' . $item->id . '.sales_periodic',
                            1, // 1 sale record
                            $purchaseTimestamp
                        );

                        $totalPeriodsLeft = 0;
                        $totalPeriodsLeftAmount = 0;
                        if (isset($cart->data['periodic_pay']['custom'])) {
                            $periods = $cart->data['periodic_pay']['custom'];
                            $totalPeriodsLeft = count($periods);
                            foreach ($periods as $period) {
                                $totalPeriodsLeftAmount += floatval($period['amount']);
                            }
                        } else if (isset($item->data['calucalte_periodic']) && isset($item->data['calucalte_periodic']['period_count'])) {
                            $calc = $item->data['calucalte_periodic'];
                            $count = intval($calc['period_count']);
                            $amount = floatval($calc['period_amount']);
                            if (isset($cart->data['gift_code']['percent'])) {
                                $gifted_products = isset($cart->data['gift_code']['products']) ? $cart->data['gift_code']['products'] : [];
                                if (in_array($item->id, $gifted_products) || count($gifted_products) === 0) {
                                    $percent = floatval($cart->data['gift_code']['percent']);
                                    $amount = ceil((1 - $percent) * $amount);
                                }
                            }
                            $totalPeriodsLeft = $count;
                            $totalPeriodsLeftAmount = $count * $amount;
                        }
                        if ($totalPeriodsLeftAmount > 0) {
                            $this->metrics->pushMeasurement(
                                $cart->domain_id,
                                'cart:' . $cart->id,
                                'product.' . $item->id . '.remain_amount',
                                -1 * $totalPeriodsLeftAmount, // remaining periods amount
                                $purchaseTimestamp
                            );
                            $this->metrics->pushMeasurement(
                                $cart->domain_id,
                                'cart:' . $cart->id,
                                'product.' . $item->id . '.remain_count',
                                -1 * $totalPeriodsLeft, // remaining periods count
                                $purchaseTimestamp
                            );
                        }

                        if (!is_null($supportProfileId) && $supportProfileTimestamp <= $purchaseTimestamp) {
                            $this->metrics->pushMeasurement(
                                $cart->domain_id,
                                'cart:' . $cart->id,
                                'product.' . $item->id . '.sales_periodic.' . $supportProfileId,
                                1, // 1 sale record
                                $purchaseTimestamp
                            );
                            if ($totalPeriodsLeftAmount > 0) {
                                $this->metrics->pushMeasurement(
                                    $cart->domain_id,
                                    'cart:' . $cart->id,
                                    'product.' . $item->id . '.remain_amount.'.$supportProfileId,
                                    -1 * $totalPeriodsLeftAmount, // remaining periods amount
                                    $purchaseTimestamp
                                );
                                $this->metrics->pushMeasurement(
                                    $cart->domain_id,
                                    'cart:' . $cart->id,
                                    'product.' . $item->id . '.remain_count.'.$supportProfileId,
                                    -1 * $totalPeriodsLeft, // remaining periods count
                                    $purchaseTimestamp
                                );
                            }
                        }

                    } else {
                        // not periodic purchase for product
                        $this->metrics->pushMeasurement(
                            $cart->domain_id,
                            'cart:' . $cart->id,
                            'product.' . $item->id . '.sales_fixed',
                            1, // 1 sale record
                            $purchaseTimestamp
                        );
                        if (!is_null($supportProfileId) && $supportProfileTimestamp <= $purchaseTimestamp) {
                            $this->metrics->pushMeasurement(
                                $cart->domain_id,
                                'cart:' . $cart->id,
                                'product.' . $item->id . '.sales_fixed.' . $supportProfileId,
                                1, // 1 sale record
                                $purchaseTimestamp
                            );
                        }
                    }
                }
            }
        }

        if (BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART)) {
            $prod_ids = [];
            if (isset($cart->data['periodic_pay']['custom']) && $cart->data['periodic_pay']['custom']) {
                $originalCart = Cart::with('products')->find($cart->data['periodic_pay']['originalCart']);
                $prod_ids = $originalCart->products->pluck('id');
            } else if (isset($cart->data['periodic_pay']['product']['id'])) {
                $prod_ids[] = $cart->data['periodic_pay']['product']['id'];
            }

            foreach ($prod_ids as $prod_id) {
                $this->metrics->pushMeasurement(
                    $cart->domain_id,
                    'cart:' . $cart->id,
                    'product.' . $prod_id . '.periodic_payment',
                    1, // 1 periodic payment record
                    $purchaseTimestamp
                );
                $this->metrics->pushMeasurement(
                    $cart->domain_id,
                    'cart:' . $cart->id,
                    'product.' . $prod_id . '.remain_amount',
                    $cart->amount, // positive value for remaining periods
                    $purchaseTimestamp
                );
                $this->metrics->pushMeasurement(
                    $cart->domain_id,
                    'cart:' . $cart->id,
                    'product.' . $prod_id . '.remain_count',
                    1, // positive value for remaining periods counts
                    $purchaseTimestamp
                );

                if (!is_null($supportProfileId) && $supportProfileTimestamp <= $purchaseTimestamp) {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        'cart:' . $cart->id,
                        'product.' . $prod_id . '.periodic_payment.' . $supportProfileId,
                        1, // 1 periodic payment record
                        $purchaseTimestamp
                    );

                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        'cart:' . $cart->id,
                        'product.' . $prod_id . '.remain_amount.'.$supportProfileId,
                        $cart->amount, // positive value for remaining periods
                        $purchaseTimestamp
                    );
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        'cart:' . $cart->id,
                        'product.' . $prod_id . '.remain_count.'.$supportProfileId,
                        1, // positive value for remaining periods counts
                        $purchaseTimestamp
                    );
                }
            }
        }
    }
}
