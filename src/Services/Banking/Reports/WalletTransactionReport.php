<?php

namespace Larapress\ECommerce\Services\Banking\Reports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Services\IReportSource;
use Larapress\ECommerce\Services\Banking\Events\BankGatewayTransactionEvent;
use Larapress\ECommerce\Services\Banking\Events\CartPurchasedEvent;
use Larapress\ECommerce\Services\Banking\Events\WalletTransactionEvent;
use Larapress\Reports\Services\BaseReportSource;
use Larapress\Reports\Services\IMetricsService;
use Larapress\Reports\Services\IReportsService;

class WalletTransactionReport implements IReportSource, ShouldQueue
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
            'user.wallet.total' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                return $this->reports->queryMeasurement(
                    'user_wallet',
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
                    'user_wallet',
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
        $supportProfileId = isset($event->transaction->user->supportProfile['id']) ? $event->transaction->user->supportProfile['id']: null;
        $tags = [
            'domain' => $event->transaction->domain_id,
            'currency' => $event->transaction->currency,
            'type' => $event->transaction->type,
            'decrease' => floatval($event->transaction->amount) > 0,
            'tr_id' => $event->transaction->id,
            'support' => $supportProfileId,
        ];
        $this->reports->pushMeasurement('user_wallet', 1, $tags, [
            'amount' => floatval($event->transaction->amount),
        ], $event->timestamp);


        $this->metrics->pushMeasurement(
            $event->transaction->domain_id,
            'wallet.'.$event->transaction->type.'.amount',
            $event->transaction->amount,
            $event->timestamp
        );

        if (!is_null($supportProfileId)) {
            $this->metrics->pushMeasurement(
                $event->transaction->domain_id,
                'wallet.'.$event->transaction->type.'.amount.'.$supportProfileId,
                $event->transaction->amount,
                $event->timestamp
            );
        }
    }
}
