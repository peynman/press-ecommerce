<?php

namespace Larapress\ECommerce\Services\Cart\Base;

use Larapress\CRUD\Extend\CastableClassArray;

/**
 * Schema representing a product purchase request in a Cart
 */
class CartProductDetails extends CastableClassArray
{
    // product price tag when added to cart
    /** @var float */
    public $amount;
    /** @var int */
    public $currency;
    /** @var int */
    public $quantity;
    // extra data
    /** @var array */
    public $extra;
    // hidden data
    /** @var array */
    public $hidden;

    protected $TYPE_CASTS = [
        'amount' => 'float',
        'currency' => 'int',
        'quantity' => 'int',
    ];
}
