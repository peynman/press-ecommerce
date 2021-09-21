<?php

namespace Larapress\ECommerce\Services\Cart\Base;

use Larapress\CRUD\Extend\CastableClassArray;

class CartCustomInstallmentPeriod extends CastableClassArray {
    /** @var float */
    public $amount;
    /** @var int */
    public $currency;
    /** @var int */
    public $index;
    /** @var Carbon */
    public $payment_at;
    /** @var Carbon */
    public $payment_paid_at;
    /** @var int */
    public $status;
    /** @var string */
    public $desc;

    protected $TYPE_CASTS = [
        'amount' => 'float',
        'currency' => 'int',
        'index' => 'int',
        'payment_at' => 'carbon',
        'payment_paid_at' => 'carbon',
        'status' => 'int',
        'desc' => 'string',
    ];
}
