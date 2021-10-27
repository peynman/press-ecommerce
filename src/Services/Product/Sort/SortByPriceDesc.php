<?php

namespace Larapress\ECommerce\Services\Product\Sort;

use Illuminate\Database\Eloquent\Builder;
use Larapress\ECommerce\Services\Product\IProductSort;

class SortByPriceDesc implements IProductSort
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
        $query->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.fixedPrice.amount')) AS float) DESC");
        return $query;
    }
}
