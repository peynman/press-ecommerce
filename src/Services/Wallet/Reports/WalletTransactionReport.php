<?php

namespace Larapress\ECommerce\Services\Wallet\Reports;

use Larapress\CRUD\ICRUDUser;
use Larapress\Reports\Services\Reports\ICRUDReportSource;
use Larapress\Reports\Services\Reports\IMetricsService;
use Larapress\Reports\Services\Reports\ReportQueryRequest;

class WalletTransactionReport implements ICRUDReportSource
{
    const NAME = 'ecommerce.wallet_transactions.windowed';

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
     *
     * @return array
     */
    public function getReport(ICRUDUser $user, ReportQueryRequest $request): array
    {
        return [];
    }
}
