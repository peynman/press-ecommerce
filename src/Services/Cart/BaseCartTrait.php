<?php

namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Cart\CartGiftDetails;

trait BaseCartTrait
{
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
    public function getPeriodicProductsCount() {
        $currIds = $this->products->pluck('id');
        $perrIds = $this->getPeriodicProductIds();
        $diff = array_diff($perrIds, $currIds);
        return count($perrIds) - count($diff);
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
        return $this->items->pluck('id');
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
     * @property int|Product $product
     * @return int
     */
    public function getPaymentsCountForInstallmentsOnProduct($product)
    {
        if ($this->isCustomPeriodicPayment()) {
            return count($this->data['periodic_custom']);
        } else {
            if (is_object($product)) {
                $product = $product->id;
            }
            $alreadyPaidPeriods = isset($this->data['periodic_payments']) ? $this->data['periodic_payments'] : [];
            return isset($alreadyPaidPeriods[$product]) ? count($alreadyPaidPeriods[$product]) : 0;
        }
    }

    /**
     * Undocumented function
     *
     * @property int|Product $product
     * @return int
     */
    public function getInstallmentsCountOnProduct($product)
    {
        if (is_object($product)) {
            $product = $product->id;
        }
        return isset($this->data['period_details'][$product]) ? $this->data['period_details'][$product]['count'] : 0;
    }

    /**
     * Undocumented function
     *
     * @property int|Product $product
     * @return bool
     */
    public function isPeriodicPaymentsCompletedOnProduct($product)
    {
        return $this->getPaymentsCountForInstallmentsOnProduct($product) >= $this->getInstallmentsCountOnProduct($product);
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getCustomPeriodsOrdered()
    {
        $periodConfig = array_map(function ($data) {
            $data['payment_at'] = Carbon::parse($data['payment_at']);
            return $data;
        }, array_filter($this->data['periodic_custom'], function ($data) {
            return isset($data['payment_at']) && !is_null($data['payment_at']);
        }));
        usort($periodConfig, function ($a, $b) {
            return $a['payment_at']->getTimestamp() - $b['payment_at']->getTimestamp();
        });
        return $periodConfig;
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
     * @return Carbon
     */
    public function getPeriodStart()
    {
        return Carbon::parse($this->data['period_start']);
    }

    /**
     * Undocumented function
     *
     * @param int|Product $product
     * @return Carbon|null
     */
    public function getNextPeriodDueDateForProduct($product)
    {
        if (is_object($product)) {
            $product = $product->id;
        }
        if ($this->isCustomPeriodicPayment()) {
            $payment_index = -1;
            $paymentInfo = null;
            $indexer = 0;
            $orderedPeriods = $this->getCustomPeriodsOrdered();
            foreach ($orderedPeriods as $custom) {
                if (isset($custom['status']) && $custom['status'] == ICart::CustomAccessStatusNotPaid) {
                    $payment_index = $indexer;
                    $paymentInfo = $custom;
                    break;
                }
                $indexer++;
            }
            if ($payment_index >= 0 && !is_null($paymentInfo) && isset($paymentInfo['amount']) && isset($paymentInfo['payment_at'])) {
                return Carbon::parse($paymentInfo['payment_at']);
            }
        } else {
            $details = $this->getPeriodicDetailsForProduct($product);
            $duration = $details['duration'];
            $total = $details['count'];
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @param [type] $productId
     * @return array|null
     */
    protected function getPeriodicDetailsForProduct($productId)
    {
        return isset($this->data['period_details'][$productId]) ? $this->data['period_details'][$productId] : null;
    }


    /**
     * Undocumented function
     *
     * @return CartGiftDetails|null
     */
    public function getGiftCodeUsage() {
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
    public function getUseBalance() {
        return isset($this->data['use_balance']) && $this->data['use_balance'];
    }


    /**
     * Undocumented function
     *
     * @param [type] $ids
     * @return void
     */
    public function setPeriodicProductIds($ids) {
        if (is_null($this->data)) { $this->data = []; }
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
    public function setUseBalance(bool $useBalance) {
        if (is_null($this->data)) { $this->data = []; }
        $this->data = array_merge($this->data, [
            'use_balance' => $useBalance,
        ]);
    }


    /**
     * Undocumented function
     *
     * @param boolean $useBalance
     * @return void
     */
    public function setGateway($gateway) {
        if (is_null($this->data)) { $this->data = []; }
        $this->data = array_merge($this->data, [
            'gateway' => $gateway,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param CartGiftDetails $details
     * @return void
     */
    public function setGiftCodeUsage(CartGiftDetails $details) {
        if (is_null($this->data)) { $this->data = []; }
        $this->data = array_merge($this->data, [
            'gift_code' => (array) $details,
        ]);
    }


    /**
     * Undocumented function
     *
     * @param Carbon $timestamp
     * @return void
     */
    public function setPeriodStart(Carbon $timestamp) {
        if (is_null($this->data)) { $this->data = []; }
        $this->data = array_merge($this->data, [
            'period_start' => (array) $timestamp,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param string|null $desc
     * @return void
     */
    public function setDescription($desc) {
        if (is_null($this->data)) { $this->data = []; }
        $this->data = array_merge($this->data, [
            'description' => (array) $desc,
        ]);
    }

}
