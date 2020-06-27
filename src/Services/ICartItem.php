<?php


namespace Larapress\ECommerce\Services;

use Illuminate\Database\Eloquent\Model;

interface ICartItem
{
    /**
     * Undocumented function
     *
     * @return float
     */
    public function price();

    /**
     * Undocumented function
     *
     * @return float
     */
    public function pricePeriodic();

        /**
     * Undocumented function
     *
     * @return float
     */
    public function pricePeriods();

    /**
     * Undocumented function
     *
     * @return int
     */
    public function currency();

    /**
     * Undocumented function
     *
     * @return String
     */
    public function product_uid();

    /**
     * Undocumented function
     *
     * @return Model
     */
    public function model();
}
