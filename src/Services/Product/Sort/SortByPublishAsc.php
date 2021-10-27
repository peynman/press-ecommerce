<?php

namespace Larapress\ECommerce\Services\Product\Sort;

use Illuminate\Database\Eloquent\Builder;
use Larapress\ECommerce\Services\Product\IProductSort;

class SortByPublishAsc implements IProductSort
{
    /**
     * Undocumented function
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function applySort(Builder $query): Builder
    {
        $query->orderBy('publish_at', 'asc');
        $query->orderBy('created_at', 'asc');
        return $query;
    }
}
