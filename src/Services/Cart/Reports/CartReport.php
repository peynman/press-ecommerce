<?php

namespace Larapress\ECommerce\Services\Cart\Reports;

use Larapress\CRUD\ICRUDUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\Reports\Services\Reports\ICRUDReportSource;
use Larapress\Reports\Services\Reports\IMetricsService;
use Larapress\Reports\Services\Reports\ReportQueryRequest;

class CartReport implements ICRUDReportSource
{
    const NAME = 'ecommerce.carts.windowed';

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
        $query = $this->metrics->measurementQuery(
            $user,
            $request,
            config('larapress.ecommerce.reports.group'),
            config('larapress.ecommerce.reports.carts'),
            $request->getAggregateFunction(),
            $request->getAggregateWindow()
        );

        $filters = $request->getFilters();
        if (isset($filters['status'])) {
            switch ($filters['status']) {
                case 'purchased':
                    $query->whereIn('data->status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED]);
                    break;
                default:
                    if (is_numeric($filters['status'])) {
                        $query->whereIn('data->status', $filters['status']);
                    }
            }
        }

        return $query->get()
            ->toArray();
    }
}
