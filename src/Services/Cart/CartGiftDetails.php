<?php

namespace Larapress\ECommerce\Services\Cart;

use stdClass;

class CartGiftDetails extends stdClass
{
    public $code_id;
    public $code;
    public $amount;
    public $products;
    public $fixed_only;
    public $percent;

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
