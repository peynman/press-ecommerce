<?php

namespace Larapress\ECommerce\Services\GiftCodes\Reports;

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

        if (config('larapress.ecommerce.reports.gift_codes')) {
            if (!$cart->isPeriodicPaymentCart() && $cart->status === Cart::STATUS_ACCESS_COMPLETE) {
                $giftCode = $cart->getGiftCodeUsage();
                if (!is_null($giftCode)) {
                    $this->metrics->updateMeasurement(
                        $cart->domain_id,
                        $cart->customer_id,
                        null,
                        $cart->customer->getMembershipGroupIds(),
                        config('larapress.ecommerce.reports.gift_codes'),
                        config('larapress.ecommerce.reports.group'),
                        'gift_code.'.$giftCode->code_id,
                        $giftCode->amount,
                        [
                            'product' => count($giftCode->products),
                        ],
                        $event->timestamp
                    );
                }
            }
        }
    }
}
