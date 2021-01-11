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
            if (
                !BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART) &&
                BaseFlags::isActive($cart->flags, Cart::FLAGS_USER_CART)
            ) {
                // add each product sales counter
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
                        if (!is_null($supportProfileId) && $supportProfileTimestamp >= $purchaseTimestamp) {
                            $this->metrics->pushMeasurement(
                                $cart->domain_id,
                                'cart:' . $cart->id,
                                'product.' . $item->id . '.sales_periodic.' . $supportProfileId,
                                1, // 1 sale record
                                $purchaseTimestamp
                            );
                        }
                    } else {
                        $this->metrics->pushMeasurement(
                            $cart->domain_id,
                            'cart:' . $cart->id,
                            'product.' . $item->id . '.sales_fixed',
                            1, // 1 sale record
                            $purchaseTimestamp
                        );
                        if (!is_null($supportProfileId) && $supportProfileTimestamp > $purchaseTimestamp) {
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
                if (!is_null($supportProfileId) && $supportProfileTimestamp >= $purchaseTimestamp) {
                    $this->metrics->pushMeasurement(
                        $cart->domain_id,
                        'cart:' . $cart->id,
                        'product.' . $prod_id . '.periodic_payment.' . $supportProfileId,
                        1, // 1 periodic payment record
                        $purchaseTimestamp
                    );
                }
            }
        }
    }
}
