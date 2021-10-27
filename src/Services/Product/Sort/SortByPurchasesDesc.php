<?php

namespace Larapress\ECommerce\Services\Product\Sort;

use Illuminate\Database\Eloquent\Builder;
use Larapress\ECommerce\Services\Product\IProductSort;

class SortByPurchasesDesc implements IProductSort
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
        return $query;
    }
}
