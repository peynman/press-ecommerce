<?php

namespace Larapress\ECommerce\Services\Product;

use Illuminate\Database\Eloquent\Builder;

interface IProductSort {
    /**
     * Undocumented function
     *
     * @param Builder $query
     *
     * @return void
     */
    public function applySort(Builder $query);
}
