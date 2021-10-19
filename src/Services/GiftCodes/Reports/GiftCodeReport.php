<?php

namespace Larapress\ECommerce\Services\Cart\Reports;

use Larapress\CRUD\ICRUDUser;
use Larapress\Reports\Services\Reports\ICRUDReportSource;
use Larapress\Reports\Services\Reports\IMetricsService;
use Larapress\Reports\Services\Reports\ReportQueryRequest;

class GiftCodeReport implements ICRUDReportSource
{
    const NAME = 'ecommerce.gift_codes.windowed';

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
