<?php


namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

interface ICartItem
{
    /**
     * Undocumented function
     *
     * @return float
     */
    public function price($currency);

    /**
     * Undocumented function
     *
     * @return bool
     */
    public function isFree();

    /**
     * Undocumented function
     *
     * @return float
     */
    public function pricePeriodic($currency);

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isQuantized();

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPeriodicSaleOnly();

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPeriodicSaleAvailable();

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

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getMaxPurchaseCountPerUser() : int;

    /**
     * Undocumented function
     *
     * @param integer $max
     * @return void
     */
    public function setMaxPurchaseCountPerUser(int $max);

    /**
     * Undocumented function
     *
     * @return Carbon|null
     */
    public function getPeriodicPurchaseEndDate();

    /**
     * Undocumented function
     *
     * @param Carbon|string $date
     * @return void
     */
    public function setPeriodicPurchaseEndDate($date);

    /**
     * Undocumented function
     *
     * @return integer
     */
    public function getPeriodicPurchaseDuration() : int;

    /**
     * Undocumented function
     *
     * @param integer $days
     * @return void
     */
    public function setPeriodicPurchaseDuration(int $days);

    /**
     * Undocumented function
     *
     * @param float $amount
     * @return void
     */
    public function setPeriodicPurchaseAmount(float $amount);

    /**
     * Undocumented function
     *
     * @return float
     */
    public function getPeriodicPurchaseAmount() : float;

    /**
     * Undocumented function
     *
     * @return integer
     */
    public function getPeriodicPurchaseCount() : int;

    /**
     * Undocumented function
     *
     * @param integer $count
     * @return void
     */
    public function setPeriodicPurchaseCount(int $count);
}
