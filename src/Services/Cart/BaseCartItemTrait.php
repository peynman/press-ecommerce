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
        if (!isset($this->data['fixedPrice']['amount'])) {
            return 0;
        }

        if (is_numeric($this->data['fixedPrice']['amount'])) {
            return floatval($this->data['fixedPrice']['amount']);
        }

        return 0;
    }

    /**
     * Undocumented function
     *
     * @return float
     */
    public function pricePeriodic($currency)
    {
        if (!isset($this->data['periodicPrice']['amount'])) {
            return 0;
        }

        if (is_numeric($this->data['periodicPrice']['amount'])) {
            return floatval($this->data['periodicPrice']['amount']);
        }

        return 0;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isQuantized()
    {
        return isset($this->data['quantized']) && $this->data['quantized'];
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPeriodicSaleOnly()
    {
        return isset($this->data['salePeriodicOnly']) && $this->data['salePeriodicOnly'];
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPeriodicSaleAvailable()
    {
        return isset($this->data['periodicPrice']['amount']) &&
            is_numeric($this->data['periodicPrice']['amount']) &&
            floatval($this->data['periodicPrice']['amount']) > 0;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isFree()
    {
        return !isset($this->data['fixedPrice']['amount']) || is_null($this->data['fixedPrice']['amount']);
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPriceZero() {
        return isset($this->data['fixedPrice']['amount']) && $this->data['fixedPrice']['amount'] === "0";
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
    public function getMaxPurchaseCountPerUser(): int
    {
        return isset($this->data['maxQuantity']) ? Carbon::parse($this->data['maxQuantity']) : 0;
    }

    /**
     * Undocumented function
     *
     * @param integer $max
     * @return void
     */
    public function setMaxPurchaseCountPerUser(int $max)
    {
        if (!isset($this->data)) {
            $this->data = [];
        }
        $this->data['maxQuantity'] = $max;
    }

    /**
     * Undocumented function
     *
     * @return Carbon|null
     */
    public function getPeriodicPurchaseEndDate()
    {
        return isset($this->data['periodicPrice']['endsAt']) ? Carbon::parse($this->data['periodicPrice']['endsAt']) : null;
    }

    /**
     * Undocumented function
     *
     * @param Carbon|string $date
     * @return void
     */
    public function setPeriodicPurchaseEndDate($date)
    {
        if (!isset($this->data['periodicPrice'])) {
            if (!isset($this->data)) {
                $this->data = [];
            }
            $this->data['periodicPrice'] = [];
        }
        if (is_string($date)) {
            $date = Carbon::parse($date);
        }
        $this->data['periodicPrice']['endsAt'] = $date;
    }

    /**
     * Undocumented function
     *
     * @return integer
     */
    public function getPeriodicPurchaseDuration(): int
    {
        return isset($this->data['periodicPrice']['periodsDuration']) ? $this->data['periodicPrice']['periodsDuration'] : 0;
    }

    /**
     * Undocumented function
     *
     * @param integer $days
     * @return void
     */
    public function setPeriodicPurchaseDuration(int $days)
    {
        if (!isset($this->data['periodicPrice'])) {
            if (!isset($this->data)) {
                $this->data = [];
            }
            $this->data['periodicPrice'] = [];
        }
        $this->data['periodicPrice']['periodsDuration'] = $days;
    }

    /**
     * Undocumented function
     *
     * @param float $amount
     * @return void
     */
    public function setPeriodicPurchaseAmount(float $amount)
    {
        if (!isset($this->data['periodicPrice'])) {
            if (!isset($this->data)) {
                $this->data = [];
            }
            $this->data['periodicPrice'] = [];
        }
        $this->data['periodicPrice']['periodsAmount'] = $amount;
    }

    /**
     * Undocumented function
     *
     * @return float
     */
    public function getPeriodicPurchaseAmount(): float
    {
        return isset($this->data['periodicPrice']['periodsAmount']) ? $this->data['periodicPrice']['periodsAmount'] : 0;
    }

    /**
     * Undocumented function
     *
     * @return integer
     */
    public function getPeriodicPurchaseCount(): int
    {
        return isset($this->data['periodicPrice']['periodCount']) ? $this->data['periodicPrice']['periodCount'] : 0;
    }

    /**
     * Undocumented function
     *
     * @param integer $count
     * @return void
     */
    public function setPeriodicPurchaseCount(int $count)
    {
        if (!isset($this->data['periodicPrice'])) {
            if (!isset($this->data)) {
                $this->data = [];
            }
            $this->data['periodicPrice'] = [];
        }
        $this->data['periodicPrice']['periodCount'] = $count;
    }
}
