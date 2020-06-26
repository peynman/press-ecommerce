<?php

namespace Larapress\ECommerce\Services;

use Larapress\Reports\Services\IReportsService;

class BankTransactionReport {
    /** @var IReportsService */
    private $reports;
    public function __construct(IReportsService $reports)
    {
        $this->reports = $reports;
    }

    public function handle(BankTransactionEvent $event)
    {
        $tags = [
            'user' => $event->transaction->customer->id,
            'domain' => $event->transaction->domain->id,
            'currency' => $event->transaction->currency,
            'status' => $event->transaction->status,
            'gateway' => $event->transaction->bank_gateway_id,
            'ip' => $event->ip,
        ];
        $this->reports->pushMeasurement('bank-transaction', $event->transaction->amount, $tags, [], time());
    }
}
