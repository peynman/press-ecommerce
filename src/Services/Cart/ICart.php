<?php

namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Cart\CartGiftDetails;

interface ICart {
    const CustomAccessStatusPaid = 1;
    const CustomAccessStatusNotPaid = 2;

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getPeriodicProductIds();

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getPeriodicProductsCount();

    /**
     * Undocumented function
     *
     * @param callable $callback
     * @return array
     */
    public function onEachPeriodicProductId($callback);

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getProductIds();

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getCustomPeriodsOrdered();

    /**
     * Undocumented function
     *
     * @param int|Product $product
     * @return boolean
     */
    public function isProductInPeriodicIds($product);

    /**
     * Undocumented function
     *
     * @property int|Product $product
     * @return int
     */
    public function getPaymentsCountForInstallmentsOnProduct($product);

    /**
     * Undocumented function
     *
     * @property int|Product $product
     * @return bool
     */
    public function isPeriodicPaymentsCompletedOnProduct($product);

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isSystemPeriodicPayment();

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isCustomPeriodicPayment();

    /**
     * Undocumented function
     *
     * @return Carbon
     */
    public function getPeriodStart();

    /**
     * Undocumented function
     *
     * @return CartGiftDetails|null
     */
    public function getGiftCodeUsage();

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function getUseBalance();

    /**
     * Undocumented function
     *
     * @param [type] $ids
     * @return void
     */
    public function setPeriodicProductIds($ids);


    /**
     * Undocumented function
     *
     * @param Carbon $timestamp
     * @return void
     */
    public function setPeriodStart(Carbon $timestamp);

    /**
     * Undocumented function
     *
     * @param string|null $desc
     * @return void
     */
    public function setDescription($desc);

    /**
     * Undocumented function
     *
     * @param boolean $useBalance
     * @return void
     */
    public function setUseBalance(bool $useBalance);


    /**
     * Undocumented function
     *
     * @param boolean $gateway
     * @return void
     */
    public function setGateway($gateway);

    /**
     * Undocumented function
     *
     * @param CartGiftDetails $details
     * @return void
     */
    public function setGiftCodeUsage(CartGiftDetails $details);
}
