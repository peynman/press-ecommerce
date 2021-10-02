<?php

namespace Larapress\ECommerce\Services\Product\Sort;

use Illuminate\Database\Eloquent\Builder;
use Larapress\ECommerce\Services\Product\IProductSort;

class SortByPriceAsc implements IProductSort
{
    /**
     * Undocumented function
     *
     * @param Builder $query
     *
     * @return void
     */
    public function applySort(Builder $query)
    {
        $query->orderByRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(data, '$.fixedPrice.amount')) AS float) ASC");
    }
}
