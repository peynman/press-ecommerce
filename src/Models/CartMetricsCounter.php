<?php

namespace Larapress\ECommerce\Models;

use Larapress\ECommerce\Services\Product\Relations\MetricCounterGroupCartRelation;
use Larapress\Reports\Models\MetricCounter;

class CartMetricsCounter extends MetricCounter
{
    /**
     * Undocumented function
     *
     * @return void
     */
    public function group_cart()
    {
        return new MetricCounterGroupCartRelation(
            $this
        );
    }
}
