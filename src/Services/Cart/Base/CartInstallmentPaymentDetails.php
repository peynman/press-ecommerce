<?php

namespace Larapress\ECommerce\Services\Cart\Base;

use Carbon\Carbon;
use Larapress\CRUD\Extend\CastableClassArray;

class CartInstallmentPaymentDetails extends CastableClassArray {
    /** @var boolean */
    public $custom;
    /** @var int */
    public $product;
    /** @var int */
    public $index;
    /** @var int */
    public $total;
    /** @var int */
    public $originalCart;
    /** @var Carbon */
    public $due_date;

    protected $TYPE_CASTS = [
        'custom' => 'bool',
        'product' => 'int',
        'index' => 'int',
        'total' => 'int',
        'originalCart' => 'int',
        'due_date' => 'carbon',
    ];
}
