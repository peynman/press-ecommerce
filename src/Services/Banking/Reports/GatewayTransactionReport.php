<?php

namespace Larapress\ECommerce\Services\Banking\Reports;

use Larapress\CRUD\ICRUDUser;
use Larapress\Reports\Services\Reports\ICRUDReportSource;
use Larapress\Reports\Services\Reports\IMetricsService;
use Larapress\Reports\Services\Reports\ReportQueryRequest;

class GatewayTransactionReport implements ICRUDReportSource
{
    const NAME = 'ecommerce.bank_transactions.windowed';

    public function __construct(public IMetricsService $metrics)
    {
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * Undocumented function
     *
     * @param ICRUDUser $user
     * @param ReportQueryRequest $request
     * @return array
     */
    public function getReport(ICRUDUser $user, ReportQueryRequest $request): array
    {
        return $this->metrics->measurementQuery(
            $user,
            $request,
            config('larapress.ecommerce.reports.group'),
            config('larapress.ecommerce.reports.bank_gateway_transactions'),
            $request->getAggregateFunction(),
            $request->getAggregateWindow(),
        )
        ->get()
        ->toArray();
    }
}
