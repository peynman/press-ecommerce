<?php

namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;

trait BaseCartItemTrait
{
    /**
     * Undocumented function
     *
     * @return float
     */
    public function price($currency)
    {
        if (!isset($this->data['pricing']) || count($this->data['pricing']) === 0) {
            return 0;
        }

        $currencyPricing = array_filter($this->data['pricing'], function($price) use($currency) {
            return (isset($price['currency']) && $price['currency'] == $currency) || !isset($price['currency']);
        });
        usort($currencyPricing, function($a, $b) {
            $pA = isset($a['priority']) ? $a['priority'] : 0;
            $pB = isset($b['priority']) ? $b['priority'] : 0;
            return $pA - $pB;
        });

        if (isset($currencyPricing[0]['currency']) && $currencyPricing[0]['currency'] == $currency)  {
            return floatval($currencyPricing[0]['amount']);
        }
        // @todo: currency convert for future

        return 0;
    }

    /**
     * Undocumented function
     *
     * @return float
     */
    public function pricePeriodic($currency)
    {
        if (!isset($this->data['price_periodic']) || count($this->data['pricing']) === 0) {
            return 0;
        }

        $currencyPricing = array_filter($this->data['price_periodic'], function($price) use($currency) {
            return (isset($price['currency']) && $price['currency'] == $currency) || !isset($price['currency']);
        });
        usort($currencyPricing, function($a, $b) {
            $pA = isset($a['priority']) ? $a['priority'] : 0;
            $pB = isset($b['priority']) ? $b['priority'] : 0;
            return $pA - $pB;
        });
        if (isset($currencyPricing[0]['currency']) && $currencyPricing[0]['currency'] == $currency)  {
            return floatval($currencyPricing[0]['amount']);
        }

        return 0;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isQuantized() {
        return isset($this->data['quantized']) && $this->data['quantized'];
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPeriodicSaleOnly() {
        return isset($this->data['sale_periodic_only']) && $this->data['sale_periodic_only'];
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPeriodicSaleAvailable() {
        return isset($this->data['price_periodic']) && count($this->data['pricing']) > 0 &&
                ((isset($this->data['is_periodic_available']) && $this->data['is_periodic_available']) || !isset($this->data['is_periodic_available']));
    }

    /**
     * Undocumented function
     *
     * @return float
     */
    public function isFree()
    {
        return
            $this->price(config('larapress.ecommerce.banking.currency')) == 0 &&
            isset($this->data['pricing']) && !is_null($this->data['pricing']) && count($this->data['pricing']) > 0;
    }

    /**
     * Undocumented function
     *
     * @return String
     */
    public function product_uid()
    {
        return 'product:' . $this->id;
    }

    /**
     * Undocumented function
     *
     * @return Model
     */
    public function model()
    {
        return $this;
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getMaxPurchaseCountPerUser() : int {
        return isset($this->data['max_quantity']) ? Carbon::parse($this->data['max_quantity']) : 0;
    }

    /**
     * Undocumented function
     *
     * @param integer $max
     * @return void
     */
    public function setMaxPurchaseCountPerUser(int $max) {
        if (!isset($this->data)) {
            $this->data = [];
        }
        $this->data['max_quantity'] = $max;
    }

    /**
     * Undocumented function
     *
     * @return Carbon|null
     */
    public function getPeriodicPurchaseEndDate() {
        return isset($this->data['calucalte_periodic']['ends_at']) ? Carbon::parse($this->data['calucalte_periodic']['ends_at']) : null;
    }

    /**
     * Undocumented function
     *
     * @param Carbon|string $date
     * @return void
     */
    public function setPeriodicPurchaseEndDate($date) {
        if (! isset($this->data['calucalte_periodic'])) {
            if (!isset($this->data)) {
                $this->data = [];
            }
            $this->data['calucalte_periodic'] = [];
        }
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        $this->data['calucalte_periodic']['ends_at'] = $date;
    }

    /**
     * Undocumented function
     *
     * @return integer
     */
    public function getPeriodicPurchaseDuration() : int {
        return isset($this->data['calucalte_periodic']['period_duration']) ? $this->data['calucalte_periodic']['period_duration'] : 0;
    }

    /**
     * Undocumented function
     *
     * @param integer $days
     * @return void
     */
    public function setPeriodicPurchaseDuration(int $days) {
        if (! isset($this->data['calucalte_periodic'])) {
            if (!isset($this->data)) {
                $this->data = [];
            }
            $this->data['calucalte_periodic'] = [];
        }
        $this->data['calucalte_periodic']['period_duration'] = $days;
    }

    /**
     * Undocumented function
     *
     * @param float $amount
     * @return void
     */
    public function setPeriodicPurchaseAmount(float $amount) {
        if (! isset($this->data['calucalte_periodic'])) {
            if (!isset($this->data)) {
                $this->data = [];
            }
            $this->data['calucalte_periodic'] = [];
        }
        $this->data['calucalte_periodic']['period_amount'] = $amount;
    }

    /**
     * Undocumented function
     *
     * @return float
     */
    public function getPeriodicPurchaseAmount() : float {
        return isset($this->data['calucalte_periodic']['period_amount']) ? $this->data['calucalte_periodic']['period_amount'] : 0;
    }

    /**
     * Undocumented function
     *
     * @return integer
     */
    public function getPeriodicPurchaseCount() : int {
        return isset($this->data['calucalte_periodic']['period_count']) ? $this->data['calucalte_periodic']['period_count'] : 0;
    }

    /**
     * Undocumented function
     *
     * @param integer $count
     * @return void
     */
    public function setPeriodicPurchaseCount(int $count) {
        if (! isset($this->data['calucalte_periodic'])) {
            if (!isset($this->data)) {
                $this->data = [];
            }
            $this->data['calucalte_periodic'] = [];
        }
        $this->data['calucalte_periodic']['period_count'] = $count;
    }
}
