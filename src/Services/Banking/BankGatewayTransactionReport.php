<?php

namespace Larapress\ECommerce\Services\Banking;

use Larapress\CRUD\Services\CRUD\ICRUDReportSource;
use Larapress\Reports\Services\Reports\ReportSourceTrait;
use Larapress\Reports\Services\Reports\IMetricsService;
use Larapress\Reports\Services\Reports\IReportsService;
use Larapress\Reports\Services\Reports\MetricsSourceProperties;
use Larapress\Reports\Services\Reports\ReportSourceProperties;

class BankGatewayTransactionReport implements ICRUDReportSource
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
        // has filter status in dot group position 1
        'gateway' => 2,
        'status' => 4,
    ];

    public function __construct()
    {
        /** @var IReportsService */
        $this->reports = app(IReportsService::class);
        /** @var IMetricsService */
        $this->metrics = app(IMetricsService::class);

        $this->avReports = [
            'reports.total' => function ($user, array $options = []) {
                $props = ReportSourceProperties::fromReportSourceOptions($user, $options);
                return $this->reports->queryMeasurement(
                    'bank.gateway',
                    $props->filters,
                    $props->groups,
                    array_merge(["_value"], $props->groups),
                    $props->from,
                    $props->to,
                    'sum()'
                );
            },
            'reports.windowed' => function ($user, array $options = []) {
                $props = ReportSourceProperties::fromReportSourceOptions($user, $options);
                return $this->reports->queryMeasurement(
                    'bank.gateway',
                    $props->filters,
                    $props->groups,
                    array_merge(["_value", "_time"], $props->groups),
                    $props->from,
                    $props->to,
                    'aggregateWindow(every: '.$props->widnow.', fn: sum)'
                );
            },
            'metrics.total' => function ($user, array $options = []) {
                $props = MetricsSourceProperties::fromReportSourceOptions($user, $options, $this->metricsDotGroups);
                return $this->metrics->queryMeasurement(
                    '^transaction\.[0-9]*$',
                    'bank_transaction',
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
                return $this->metrics->aggregateMeasurement(
                    '^transaction\.[0-9]*$',
                    'bank_transaction',
                    null,
                    $props->filters,
                    $props->groups,
                    $props->domains,
                    $props->from,
                    $props->to,
                    $props->window,
                );
            },
        ];
    }

    public function handle(BankGatewayTransactionEvent $event)
    {
        $transaction = $event->getGatewayTransaction();

        if (config('larapress.reports.reports.reports_service')) {
            $tags = [
                'domain' => $transaction->domain_id,
                'currency' => $transaction->currency,
                'tr_id' => $transaction->id,
                'gateway' => $transaction->bank_gateway_id,
            ];
            $this->reports->pushMeasurement('bank.gateway', $transaction->amount, $tags, [
                'status' => $transaction->status,
            ], $event->timestamp);
        }

        if (config('larapress.reports.reports.metrics_table')) {
            $this->metrics->pushMeasurement(
                $transaction->domain_id,
                'bank_transaction',
                'bank_transaction:'.$transaction->id,
                'bank_gateway.'.$transaction->bank_gateway_id.'transaction.'.$transaction->status,
                $transaction->amount,
                $event->timestamp,
            );
        }
    }
}
