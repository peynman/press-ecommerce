<?php

namespace Larapress\ECommerce\Services\Cart\Base;

use Larapress\CRUD\Extend\CastableClassArray;

class CartGiftDetails extends CastableClassArray
{
    /** @var int */
    public $code_id;
    /** @var string */
    public $code;
    /** @var float */
    public $amount;
    /** @var int[] */
    public $products;
    /** @var boolean */
    public $fixed_only;
    /** @var float */
    public $percent;
    /** @var boolean */
    public $restrict_products;
    /** @var string */
    public $mode;

    protected $TYPE_CASTS = [
        'code_id' => 'int',
        'code' => 'string',
        'amount' => 'float',
        'fixed_only' => 'bool',
        'percent' => 'float',
        'restrict_products' => 'bool',
        'mode' => 'string',
    ];
}
