<?php

namespace Larapress\ECommerce\Services\Product;

use Carbon\CarbonInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Services\IReportSource;
use Larapress\CRUD\Repository\IRoleRepository;
use Larapress\Pages\Services\PageVisitEvent;
use Larapress\Profiles\Models\FormEntry;
use Larapress\Reports\Services\BaseReportSource;
use Larapress\Reports\Services\IMetricsService;
use Larapress\Reports\Services\IReportsService;

class ProductReports implements
    IReportSource,
    ShouldQueue
{
    use BaseReportSource;

    /** @var IReportsService */
    private $reports;
    /** @var IMetricsService */
    private $metrics;

    /** @var array */
    private $avReports;

    public function filterOnProductOwner($user, $filters)
    {
        if ($user->hasRole(config('larapress.ecommerce.lms.owner_role_id'))) {
            unset($filters['domains']);
            $filters['product'] = $user->getOwenedProductsIds();
            return $filters;
        }
        return $filters;
    }

    public function __construct(IReportsService $reports, IMetricsService $metrics)
    {
        $this->reports = $reports;
        $this->metrics = $metrics;
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
            'products.sales.total' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                $filters = $this->filterOnProductOwner($user, $filters);
                $domains = [];
                if (isset($filters['domain'])) {
                    $domains = $filters['domain'];
                    unset($filters['domain']);
                }

                if (isset($filters['support'])) {
                } else {
                    $queryKey = 'product.%.sales.%.amount';
                }

                // dot nonation grouping for key name
                $dotGroups = [];
                if (in_array('product', $groups) || isset($filters['type'])) {
                    $dotGroups['type'] = 4;
                }
                if (in_array('product', $groups) || isset($filters['product'])) {
                    $dotGroups['product'] = 2;
                }

                return $this->metrics->aggregateMeasurementDotGrouped(
                    $queryKey,
                    $filters,
                    // dot nonation grouping for key name
                    $dotGroups,
                    $domains,
                    $fromC,
                    $toC,
                );
            },
            'products.sales.windowed' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                $window = $this->dateIntervalToSeconds(CarbonInterval::fromString(isset($options['window']) ? $options['window'] : '1h'));
                $filters = $this->filterOnProductOwner($user, $filters);
                $domains = [];
                if (isset($filters['domain'])) {
                    $domains = $filters['domain'];
                    unset($filters['domain']);
                }

                if (isset($filters['support'])) {
                } else {
                    $queryKey = 'product.%.sales.%.amount';
                }

                // dot nonation grouping for key name
                $dotGroups = [];
                if (isset($filters['type'])) {
                    $dotGroups['type'] = 4;
                }
                if (in_array('product', $groups) || isset($filters['product'])) {
                    $dotGroups['product'] = 2;
                }

                return $this->metrics->queryMeasurement(
                    $queryKey,
                    $window,
                    $filters,
                    // dot nonation grouping for key name
                    $dotGroups,
                    $domains,
                    $fromC,
                    $toC
                );
            },
        ];
    }

    function dateIntervalToSeconds($dateInterval)
    {
        $reference = new DateTimeImmutable();
        $endTime = $reference->add($dateInterval);

        return $endTime->getTimestamp() - $reference->getTimestamp();
    }
}
