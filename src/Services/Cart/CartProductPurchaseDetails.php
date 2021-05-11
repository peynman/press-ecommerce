<?php

namespace Larapress\ECommerce\Services\Cart;

use stdClass;

class CartProductPurchaseDetails extends stdClass
{
    public $salePrice;
    public $quantity;
    public $virtualSale;
    public $realSale;
    public $goodsSale;
    public $offAmount;
    public $paidPeriods;
    public $periodsOffPercent;
    public $periodsAmount;
    public $periodsEnds;
    public $periodsCount;
    public $periodsDuration;

    function __construct($payload)
    {
        if (is_array($payload)) {
            $this->from_array($payload);
        }
    }

    public function from_array($array)
    {
        foreach (get_object_vars($this) as $attrName => $attrValue) {
            $this->{$attrName} = $array[$attrName];
        }
    }
}
