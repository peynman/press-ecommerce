<?php

namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\CartItem;
use Larapress\ECommerce\Models\Product;
use Larapress\CRUD\BaseFlags;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\Services\Cart\Base\CartCustomInstallmentPeriod;
use Larapress\ECommerce\Services\Cart\Base\CartGiftDetails;
use Larapress\ECommerce\Services\Cart\Base\CartProductPurchaseDetails;
use Larapress\Profiles\Models\PhysicalAddress;

trait BaseCartTrait
{
    /**
     * Undocumented function
     *
     * @return ICartItem
     */
    public function getCartItems()
    {
        return $this->cart_items;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getPeriodicProductIds()
    {
        $ids = isset($this->data['periodic_product_ids']) ? $this->data['periodic_product_ids'] : [];
        if (isset($ids[0]['id'])) {
            return array_map(function ($ii) {
                return $ii['id'];
            }, $ids);
        }
        return $ids;
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getPeriodicProductsCount()
    {
        $currIds = $this->products->pluck('id')->toArray();
        $perrIds = $this->getPeriodicProductIds();
        $diff = array_diff($perrIds, $currIds);
        return count($perrIds) - count($diff);
    }


    /**
     * Undocumented function
     *
     * @return int
     */
    public function hasPeriodicProducts()
    {
        return $this->getPeriodicProductsCount() > 0;
    }

    /**
     * Undocumented function
     *
     * @param callable $callback
     * @return array
     */
    public function onEachPeriodicProductId($callback)
    {
        $ids = $this->getPeriodicProductIds();
        foreach ($ids as $id) {
            $callback($id);
        }
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getProductIds()
    {
        return $this->products->pluck('id');
    }

    /**
     * Undocumented function
     *
     * @param int|Product $product
     * @return boolean
     */
    public function isProductInPeriodicIds($product)
    {
        if (is_object($product)) {
            $product = $product->id;
        }

        return in_array($product, $this->getPeriodicProductIds());
    }

    /**
     * Undocumented function
     *
     * @param Product|int $product
     *
     * @return CartProductPurchaseDetails|null
     */
    public function getPurchaseDetailsForProduct($product)
    {
        $productPivotData = null;
        if (is_object($product)) {
            if (isset($product->pivot->data['paidPeriods'])) {
                $productPivotData = $product->pivot->data;
            }
            $product = $product->id;
        }

        if (is_null($productPivotData)) {
            // find appropriate cart item to extract CartProductPurchaseDetails
            if ($this->isPeriodicPaymentCart()) {
                // use original cart pivot data if this is a periodic payment cart
                $pivot = CartItem::where('product_id', $product)->where('cart_id', $this->getPeriodicPaymentOriginalCartID())->first();
                if (!is_null($pivot)) {
                    $productPivotData = $pivot->data;
                }
            } else {
                // use current cart pivot data
                $pivot = CartItem::where('product_id', $product)->where('cart_id', $this->id)->first();
                if (!is_null($pivot)) {
                    $productPivotData = $pivot->data;
                }
            }
        }

        if (!is_null($productPivotData)) {
            /** @var CartItem */
            return new CartProductPurchaseDetails($productPivotData);
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getTotalQuantity()
    {
        $quantity = 0;
        $products = $this->products;
        foreach ($products as $product) {
            $details = new CartProductPurchaseDetails($product->pivot->data);
            $quantity += $details->quantity;
        }

        return $quantity;
    }

    /**
     * Undocumented function
     *
     * @property int|Product $product
     * @return int
     */
    public function getPaymentsCountForInstallmentsOnProduct($product)
    {
        $purchaseDetails = $this->getPurchaseDetailsForProduct($product);
        if (!is_null($purchaseDetails)) {
            return $purchaseDetails->paidPeriods;
        }

        throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
    }

    /**
     * Undocumented function
     *
     * @property int|Product $product
     * @return int
     */
    public function getInstallmentsCountOnProduct($product)
    {
        $purchaseDetails = $this->getPurchaseDetailsForProduct($product);
        if (!is_null($purchaseDetails)) {
            return $purchaseDetails->periodsCount;
        }

        throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
    }

    /**
     * Undocumented function
     *
     * @property int|Product $product
     * @return bool
     */
    public function isPeriodicPaymentsCompletedOnProduct($product)
    {
        $purchaseDetails = $this->getPurchaseDetailsForProduct($product);
        if (!is_null($purchaseDetails)) {
            return $purchaseDetails->paidPeriods >= $purchaseDetails->periodsCount;
        }

        throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
    }

    /**
     * Undocumented function
     *
     * @return CartCustomInstallmentPeriod[]
     */
    public function getCustomPeriodsOrdered()
    {
        /** @var CartCustomInstallmentPeriod[] */
        $periodsConfig = array_map(
            function ($data) {
                return new CartCustomInstallmentPeriod($data);
            },
            $this->data['periodic_custom']
        );

        $periodsConfig = array_filter($periodsConfig, function (CartCustomInstallmentPeriod $period) {
            return !is_null($period->payment_at);
        });

        usort($periodsConfig, function ($a, $b) {
            return $a['payment_at']->getTimestamp() - $b['payment_at']->getTimestamp();
        });

        $indexer = 0;
        return array_map(function (CartCustomInstallmentPeriod $details) use ($indexer) {
            $details->index = $indexer++;
        }, $periodsConfig);
    }

    /**
     * Undocumented function
     *
     * @param CartCustomInstallmentPeriod $customInstallments
     *
     * @return void
     */
    public function setCustomPeriodInstallments($customInstallments)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'periodic_custom' => $customInstallments,
        ]);
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isSystemPeriodicPayment()
    {
        return !$this->isCustomPeriodicPayment();
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isCustomPeriodicPayment()
    {
        return isset($this->data['periodic_custom']) && count($this->data['periodic_custom']) > 0;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPeriodicPaymentCart()
    {
        return BaseFlags::isActive($this->flags, Cart::FLAGS_PERIOD_PAYMENT_CART);
    }

    /**
     * Undocumented function
     *
     * @return Cart|null
     */
    public function getPeriodicPaymentOriginalCart()
    {
        if (isset($this->data['periodic_pay']['originalCart'])) {
            return Cart::find($this->data['periodic_pay']['originalCart']);
        }
        return null;
    }

    /**
     * Undocumented function
     *
     * @return int|null
     */
    public function getPeriodicPaymentOriginalCartID()
    {
        if (isset($this->data['periodic_pay']['originalCart'])) {
            return $this->data['periodic_pay']['originalCart'];
        }
        return null;
    }

    /**
     * Undocumented function
     *
     * @return Carbon
     */
    public function getPeriodStart()
    {
        return Carbon::parse($this->data['period_start']);
    }

    /**
     * Undocumented function
     *
     * @param int|Product|null $product
     * @return Carbon|null
     */
    public function getNextPeriodDueDateForProduct($product)
    {
        if (is_object($product)) {
            $product = $product->id;
        }

        if (!$this->isCustomPeriodicPayment()) {
            $details = $this->getPurchaseDetailsForProduct($product);

            $periodStart = $this->getPeriodStart();
            $duration = $details->periodsDuration;
            $total = $details->periodsCount;
            $periodEnd = $details->periodsEnds;
            $daysRemaining = $periodStart->diffInDays($periodEnd);
            if ($daysRemaining < $duration * $total) {
                $duration = floor($daysRemaining / $total);
            }
            return $periodStart->addDays($duration * ($details->paidPeriods + 1));
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @return CartCustomInstallmentPeriod|null
     */
    public function getNextPeriodForCustomInstallments()
    {
        if ($this->isCustomPeriodicPayment()) {
            $periodDetails = null;
            $orderedPeriods = $this->getCustomPeriodsOrdered();
            foreach ($orderedPeriods as $custom) {
                if ($custom->status === ICart::CustomAccessStatusNotPaid) {
                    $periodDetails = $custom;
                    break;
                }
            }

            return $periodDetails;
        }

        return null;
    }


    /**
     * Undocumented function
     *
     * @return CartGiftDetails|null
     */
    public function getGiftCodeUsage()
    {
        if (isset($this->data['gift_code'])) {
            return new CartGiftDetails($this->data['gift_code']);
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function getUseBalance()
    {
        return isset($this->data['use_balance']) && $this->data['use_balance'];
    }


    /**
     * Undocumented function
     *
     * @param array $ids
     * @return void
     */
    public function setPeriodicProductIds(array $ids)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'periodic_product_ids' => $ids,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param boolean $useBalance
     * @return void
     */
    public function setUseBalance(bool $useBalance)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'use_balance' => $useBalance,
        ]);
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isSingleInstallmentCart()
    {
        return $this->flags & Cart::FLGAS_SINGLE_INSTALLMENT;
    }

    /**
     * Undocumented function
     *
     * @return ICart[]
     */
    public function getSingleInstallmentOriginalCarts()
    {
        $cartIds = isset($this->data['single_installment_carts']) ? $this->data['single_installment_carts'] : [];
        return Cart::whereIn('id', $cartIds);
    }

    /**
     * Undocumented function
     *
     * @param array $names
     * @return void
     */
    public function setAvailableDeliveryAgents($names)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'delivery_agent_names' => $names,
        ]);
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getAvailableDeliveryAgents()
    {
        if (isset($this->data['delivery_agent_names'])) {
            return $this->data['delivery_agent_names'];
        }

        return [];
    }


    /**
     * Undocumented function
     *
     * @param int $addressId
     * @return void
     */
    public function setDeliveryAddress($addressId)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'delivery_address_id' => $addressId,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param int|Carbon $timestamp
     * @return void
     */
    public function setDeliveryPreferredTimestamp($timestamp)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'delivery_timestamp' => $timestamp,
        ]);
    }

    /**
     * Undocumented function
     *
     * @return null|int
     */
    public function getDeliveryAddressId()
    {
        if (isset($this->data['delivery_address_id'])) {
            return $this->data['delivery_address_id'];
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @return null|PhysicalAddress
     */
    public function getDeliveryAddress()
    {
        if (!is_null($this->getDeliveryAddressId())) {
            return PhysicalAddress::find($this->getDeliveryAddressId());
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @param string $agentName
     * @return void
     */
    public function setDeliveryAgentName($agentName)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'delivery_agent' => $agentName,
        ]);
    }

    /**
     * Undocumented function
     *
     * @return string|null
     */
    public function getDeliveryAgentName()
    {
        if (isset($this->data['delivery_agent'])) {
            return $this->data['delivery_agent'];
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @param float $price
     * @return void
     */
    public function setDeliveryPrice($price)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'delivery_price' => $price,
        ]);
    }

    /**
     * Undocumented function
     *
     * @return null|float
     */
    public function getDeliveryPrice()
    {
        if (isset($this->data['delivery_price'])) {
            return $this->data['delivery_price'];
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @return null|Carbon
     */
    public function getPreferredDeliveryTimestamp()
    {
        if (isset($this->data['delivery_timestamp'])) {
            return Carbon::parse($this->data['delivery_timestamp']);
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function setSingleInstallmentCarts(array $carts)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'single_installment_carts' => $carts,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param boolean $useBalance
     * @return void
     */
    public function setGateway($gateway)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'gateway' => $gateway,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param [type] $promotions
     * @return void
     */
    public function setPromotions($promotions) {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'promotions' => $promotions,
        ]);
    }


    /**
     * Undocumented function
     *
     * @return void
     */
    public function getPromotions() {
        if (isset($this->data['promotions']) && is_array($this->data['promotions'])) {
            $promots = [];
            foreach ($this->data['promotions'] as $promotion) {
                $promots[] = new CartGiftDetails($promotion);
            }
            return $promots;
        }

        return [];
    }

    /**
     * Undocumented function
     *
     * @param CartGiftDetails $details
     * @return void
     */
    public function setGiftCodeUsage(CartGiftDetails $details)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'gift_code' => $details->toArray(),
        ]);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function removeGiftCodeUsage()
    {
        $this->data = array_merge($this->data, [
            'gift_code' => null,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param Carbon $timestamp
     * @return void
     */
    public function setPeriodStart(Carbon $timestamp)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'period_start' => $timestamp->format(config('larapress.crud.datetime-format')),
        ]);
    }

    /**
     * Undocumented function
     *
     * @param string|null $desc
     * @return void
     */
    public function setDescription($desc)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'description' => $desc,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param string $url
     *
     * @return void
     */
    public function setSuccessRedirect($url)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'success_redirect' => $url,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param string $url
     *
     * @return void
     */
    public function setFailedRedirect($url)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'failed_redirect' => $url,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param string $url
     *
     * @return void
     */
    public function setCanceledRedirect($url)
    {
        if (is_null($this->data)) {
            $this->data = [];
        }
        $this->data = array_merge($this->data, [
            'canceled_redirect' => $url,
        ]);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getSuccessRedirect()
    {
        if (isset($this->data['success_redirect'])) {
            return $this->data['success_redirect'];
        }

        return config('larapress.ecommerce.banking.redirect.success');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getFailedRedirect()
    {
        if (isset($this->data['failed_redirect'])) {
            return $this->data['failed_redirect'];
        }

        return config('larapress.ecommerce.banking.redirect.failed');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getCanceledRedirect()
    {
        if (isset($this->data['canceled_redirect'])) {
            return $this->data['canceled_redirect'];
        }

        return config('larapress.ecommerce.banking.redirect.canceled');
    }
}
