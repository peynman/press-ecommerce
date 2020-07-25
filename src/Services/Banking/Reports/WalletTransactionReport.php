<?php

namespace Larapress\ECommerce\Services\Banking\Reports;

use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Services\IReportSource;
use Larapress\ECommerce\Services\Banking\Events\BankGatewayTransactionEvent;
use Larapress\ECommerce\Services\Banking\Events\CartPurchasedEvent;
use Larapress\ECommerce\Services\Banking\Events\WalletTransactionEvent;
use Larapress\Reports\Services\BaseReportSource;
use Larapress\Reports\Services\IReportsService;

class WalletTransactionReport implements IReportSource
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
            'user.wallet.total' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                return $this->reports->queryMeasurement(
                    'bank.gateway',
                    $filters,
                    $groups,
                    array_merge(["_value"], $groups),
                    $fromC,
                    $toC,
                    'count()'
                );
            },
            'user.wallet.windowed' => function ($user, array $options = []) {
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

    public function handle(WalletTransactionEvent $event)
    {
        $tags = [
            'domain' => $event->domain->id,
            'currency' => $event->transaction->currency,
            'tr_id' => $event->transaction->id,
        ];
        $this->reports->pushMeasurement('user.wallet', 1, $tags, [
            'amount' => $event->transaction->amount,
            'status' => $event->transaction->status,
        ], $event->timestamp);
    }
}
