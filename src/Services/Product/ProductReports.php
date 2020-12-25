<?php

namespace Larapress\ECommerce\Services\Product;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Services\IReportSource;
use Larapress\CRUD\Repository\IRoleRepository;
use Larapress\Pages\Services\PageVisitEvent;
use Larapress\Profiles\Models\FormEntry;
use Larapress\Reports\Services\BaseReportSource;
use Larapress\Reports\Services\IReportsService;

class ProductReports implements IReportSource, ShouldQueue
{
    use BaseReportSource;

    /** @var IReportsService */
    private $reports;

    /** @var array */
    private $avReports;

    public function filterOnProductOwner($user, $filters) {
        if ($user->hasRole(config('larapress.ecommerce.lms.owner_role_id'))) {
            unset($filters['domains']);
            $filters['product'] = $user->getOwenedProductsIds();
            return $filters;
        }
        return $filters;
    }

    public function __construct(IReportsService $reports)
    {
        $this->reports = $reports;
        $this->avReports = [
            'products.visit.total' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                $groups[] = "product";
                $filters = $this->filterOnProductOwner($user, $filters);

                return $this->reports->queryMeasurement(
                    'pages.visit',
                    $filters,
                    $groups,
                    array_merge(["_value"], $groups),
                    $fromC,
                    $toC,
                    'count()'
                );
            },
            'products.visit.windowed' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                $window = isset($options['window']) ? $options['window'] : '1h';
                $groups[] = "product";
                $filters = $this->filterOnProductOwner($user, $filters);

                return $this->reports->queryMeasurement(
                    'pages.visit',
                    $filters,
                    $groups,
                    array_merge(["_value", "_time"], $groups),
                    $fromC,
                    $toC,
                    'aggregateWindow(every: ' . $window . ', fn: sum) ' . (isset($options['func']) && is_string($options['func']) ? $options['func'] : '')
                );
            },
            'products.visit.func' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                $groups[] = "product";
                $filters = $this->filterOnProductOwner($user, $filters);

                return $this->reports->queryMeasurement(
                    'pages.visit',
                    $filters,
                    $groups,
                    array_merge(["_value"], $groups),
                    $fromC,
                    $toC,
                    isset($options['func']) ? $options['func'] : 'count()',
                );
            },
        ];
    }
}
