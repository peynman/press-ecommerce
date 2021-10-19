<?php

namespace Larapress\ECommerce\Services\Banking\Reports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\ECommerce\Services\Banking\BankGatewayTransactionEvent;
use Larapress\Reports\Services\Reports\IMetricsService;

class GatewayTransactionListener implements ShouldQueue
{
    const KEY = 'bank.gateway';

    public function __construct(public IMetricsService $metrics)
    {
    }

    public function handle(BankGatewayTransactionEvent $event)
    {
        $transaction = $event->getGatewayTransaction();

        if (config('larapress.ecommerce.reports.bank_gateway_transactions')) {
            $this->metrics->updateMeasurement(
                $transaction->domain_id,
                $transaction->customer_id,
                null,
                $transaction->customer->getMembershipGroupIds(),
                config('larapress.ecommerce.reports.bank_gateway_transactions'),
                config('larapress.ecommerce.reports.group'),
                self::KEY,
                $transaction->amount,
                [
                    'gateway' => $transaction->bank_gateway_id,
                    'status' => $transaction->status,
                    'cart' => $transaction->cart_id,
                ],
                $event->timestamp
            );
        }
    }
}
