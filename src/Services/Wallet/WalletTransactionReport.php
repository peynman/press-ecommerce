<?php

namespace Larapress\ECommerce\Services\Wallet;

use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\CRUD\Services\CRUD\ICRUDReportSource;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\Reports\Services\Reports\ReportSourceTrait;
use Larapress\Reports\Services\Reports\IMetricsService;
use Larapress\Reports\Services\Reports\IReportsService;
use Larapress\Reports\Services\Reports\MetricsSourceProperties;

class WalletTransactionReport implements ICRUDReportSource, ShouldQueue
{
    use ReportSourceTrait;

    /** @var IReportsService */
    private $reports;

    /** @var IMetricsService */
    private $metrics;

    /** @var array */
    private $avReports;

    // start dot groups from 1 position_1.position_2.position_3...
    private $metricsDotGroups = [
        'type' => 2,
    ];

    public function __construct()
    {
        /** @var IReportsService */
        $this->reports = app(IReportsService::class);
        /** @var IMetricsService */
        $this->metrics = app(IMetricsService::class);

        $this->avReports = [
            'metrics.total' => function ($user, array $options = []) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options, $this->metricsDotGroups);
                return $this->metrics->queryMeasurement(
                    '^transactions\.[0-9]*$',
                    'wallet_transaction',
                    null,
                    $props->filters,
                    $props->groups,
                    $props->domains,
                    $props->from,
                    $props->to
                );
            },
            'metrics.windowed' => function ($user, array $options = []) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options, $this->metricsDotGroups);
                return $this->metrics->queryMeasurement(
                    '^transactions\.[0-9]*$',
                    'wallet_transaction',
                    null,
                    $props->filters,
                    $props->groups,
                    $props->domains,
                    $props->from,
                    $props->to
                );
            }
        ];
    }

    public function handle(WalletTransactionEvent $event)
    {
        /** @var WalletTransaction */
        $transaction = $event->getWalletTransaction();

        if (config('larapress.reports.reports.reports_service')) {
            $tags = [
                'domain' => $transaction->domain_id,
                'currency' => $transaction->currency,
                'transaction' => $transaction->id,
                'type' => $transaction->type,
            ];
            $this->reports->pushMeasurement('carts.purchased', $transaction->amount, $tags, [], $event->timestamp);
        }

        if (config('larapress.reports.reports.metrics_table')) {
            $this->metrics->pushMeasurement(
                $transaction->domain_id,
                'wallet_transaction',
                'transaction:' . $transaction->id,
                'transactions.' . $transaction->type,
                $transaction->amount,
                $event->timestamp
            );
        }
    }
}
