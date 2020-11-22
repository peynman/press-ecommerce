<?php

namespace Larapress\ECommerce\Models;

use Larapress\ECommerce\Services\Product\IProductService;

trait ProductCartItem
{
    /**
     * Undocumented function
     *
     * @return float
     */
    public function price()
    {
        if (!isset($this->data['pricing'])) {
            return $this->pricePeriodic();
        }

        if (!is_null($this->data['pricing']) && count($this->data['pricing']) > 0) {
            $prices = $this->data['pricing'];
            $prior = $prices[0];
            if (!isset($prior['priority'])) {
                $prior['priority'] = 0;
            }
            foreach ($prices as $price) {
                if (isset($price['priority'])) {
                    if ($price['priority'] > $prior['priority']) {
                        $prior = $price;
                    }
                }
            }

            return floatval($prior['amount']);
        }

        return 0;
    }

    /**
     * Undocumented function
     *
     * @return float
     */
    public function isFree()
    {
        return $this->price() == 0 && isset($this->data['pricing']) && !is_null($this->data['pricing']) && count($this->data['pricing']) > 0;
    }
    /**
     * Undocumented function
     *
     * @return float
     */
    public function pricePeriodic()
    {
        if (!isset($this->data['price_periodic'])) {
            return 0;
        }

        if (!is_null($this->data['price_periodic']) && count($this->data['price_periodic']) > 0) {
            $prices = $this->data['price_periodic'];
            $prior = $prices[0];
            if (!isset($prior['priority'])) {
                $prior['priority'] = 0;
            }
            foreach ($prices as $price) {
                if (isset($price['priority'])) {
                    if ($price['priority'] > $prior['priority']) {
                        $prior = $price;
                    }
                }
            }

            return floatval($prior['amount']);
        }

        return 0;
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function currency()
    {
        return config('larapress.ecommerce.banking.currency.id');
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
     * @return array
     */
    public function getPriceTagAttribute() {
        if ($this->isFree()) {
            return [ 'amount' => 0, 'currency' => $this->currency() ];
        } else {
            if ($this->price() === 0 && !is_null($this->parent_id)) {
                return [ 'amount' => 'Parent', 'currency' => $this->currency() ];
            }

            return [ 'amount' => $this->price(), 'currency' => $this->currency() ];
        }
    }
}
