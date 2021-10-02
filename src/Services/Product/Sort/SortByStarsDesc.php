<?php

namespace Larapress\ECommerce\Services\Product\Sort;

use Illuminate\Database\Eloquent\Builder;
use Larapress\ECommerce\Services\Product\IProductSort;

class SortByStarsDesc implements IProductSort
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

    }
}
