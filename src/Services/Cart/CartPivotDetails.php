<?php

namespace Larapress\ECommerce\Services\Cart;

use stdClass;

class CartPivotDetails extends stdClass
{
    public $amount = 0;
    public $quantity = 1;
    public $offAmount = 0;

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
