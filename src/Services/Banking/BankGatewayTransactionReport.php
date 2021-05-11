<?php

namespace Larapress\ECommerce\Services\Banking;

use Larapress\CRUD\Services\CRUD\ICRUDReportSource;
use Larapress\Reports\Services\BaseReportSource;
use Larapress\Reports\Services\IReportsService;

class BankGatewayTransactionReport implements ICRUDReportSource
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
            'bank.gateway.total' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                return $this->reports->queryMeasurement(
                    'bank.gateway',
                    $filters,
                    $groups,
                    array_merge(["_value"], $groups),
                    $fromC,
                    $toC,
                    'sum()'
                );
            },
            'bank.gateway.windowed' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                $window = isset($options['window']) ? $options['window'] : '1h';
                return $this->reports->queryMeasurement(
                    'bank.gateway',
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

    public function handle(BankGatewayTransactionEvent $event)
    {
        $transaction = $event->getGatewayTransaction();
        $tags = [
            'domain' => $event->domainId,
            'currency' => $transaction->currency,
            'tr_id' => $transaction->id,
            'gateway' => $transaction->bank_gateway_id,
            'status' => $transaction->status,
        ];
        $this->reports->pushMeasurement('bank.gateway', intval($transaction->amount), $tags, [], $event->timestamp);
    }
}
