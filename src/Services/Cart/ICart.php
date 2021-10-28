<?php

namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Cart\Base\CartGiftDetails;
use Larapress\ECommerce\Services\Cart\Base\CartCustomInstallmentPeriod;
use Larapress\Profiles\Models\PhysicalAddress;

interface ICart
{
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
     * @return int
     */
    public function getTotalQuantity();

    /**
     * Undocumented function
     *
     * @return int
     */
    public function hasPeriodicProducts();

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPeriodicPaymentCart();

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
     * @return CartCustomInstallmentPeriod[]
     */
    public function getCustomPeriodsOrdered();

    /**
     * Undocumented function
     *
     * @return ICart
     */
    public function getPeriodicPaymentOriginalCart();

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
     * @return boolean
     */
    public function isSingleInstallmentCart();

    /**
     * Undocumented function
     *
     * @return ICart[]
     */
    public function getSingleInstallmentOriginalCarts();

    /**
     * Undocumented function
     *
     * @return void
     */
    public function setSingleInstallmentCarts(array $carts);

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
    public function setPeriodicProductIds(array $ids);

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

    /**
     * Undocumented function
     *
     * @param CartCustomInstallmentPeriod[] $customInstallments
     *
     * @return void
     */
    public function setCustomPeriodInstallments($customInstallments);


    /**
     * Undocumented function
     *
     * @param array $names
     * @return void
     */
    public function setAvailableDeliveryAgents($names);

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getAvailableDeliveryAgents();

    /**
     * Undocumented function
     *
     * @param int $addressId
     * @return void
     */
    public function setDeliveryAddress($addressId);

    /**
     * Undocumented function
     *
     * @param int|Carbon $timestamp
     * @return void
     */
    public function setDeliveryPreferredTimestamp($timestamp);

    /**
     * Undocumented function
     *
     * @return null|int
     */
    public function getDeliveryAddressId();

    /**
     * Undocumented function
     *
     * @return null|PhysicalAddress
     */
    public function getDeliveryAddress();

    /**
     * Undocumented function
     *
     * @param string $agentName
     * @return void
     */
    public function setDeliveryAgentName($agentName);

    /**
     * Undocumented function
     *
     * @return string|null
     */
    public function getDeliveryAgentName();

    /**
     * Undocumented function
     *
     * @param float $price
     * @return void
     */
    public function setDeliveryPrice($price);

    /**
     * Undocumented function
     *
     * @return null|float
     */
    public function getDeliveryPrice();

    /**
     * Undocumented function
     *
     * @return null|Carbon
     */
    public function getPreferredDeliveryTimestamp();

    /**
     * Undocumented function
     *
     * @param string $url
     *
     * @return void
     */
    public function setSuccessRedirect($url);

    /**
     * Undocumented function
     *
     * @param string $url
     *
     * @return void
     */
    public function setFailedRedirect($url);

    /**
     * Undocumented function
     *
     * @param string $url
     *
     * @return void
     */
    public function setCanceledRedirect($url);

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getSuccessRedirect();

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getFailedRedirect();

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getCanceledRedirect();

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products();

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer();

    /**
     * Undocumented function
     *
     * @return ICartItem
     */
    public function getCartItems();
}
