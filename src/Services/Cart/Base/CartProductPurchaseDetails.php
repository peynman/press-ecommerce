<?php

namespace Larapress\ECommerce\Services\Cart\Base;

use Carbon\Carbon;
use Larapress\CRUD\Extend\CastableClassArray;

class CartProductPurchaseDetails extends CastableClassArray
{
    // product price tag when purchased (total or first period) times qunatity
    /** @var float */
    public $amount;
    // product periods price tag when purchased
    /** @var float */
    public $periodsAmount;
    // product quantity in cart
    /** @var int */
    public $quantity;
    // product off amount from gift
    /** @var float */
    public $offAmount;
    // product price tag when purchased
    /** @var float */
    public $fee;

    // extra purchase info
    /** @var array */
    public $extra;

    // product paid amount in currency from cart
    /** @var float */
    public $currencyPaid;
    // virtual sale amount for this purchase
    /** @var float */
    public $virtualSale;
    // real sale amount for this purchase
    /** @var float */
    public $realSale;
    // total sale (fixed + not yet periods) for this purchase
    /** @var float */
    public $goodsSale;

    // is product purchased periodic
    /** @var bool */
    public $hasPeriods;
    // count of periodic payments made on this product
    /** @var int */
    public $paidPeriods;
    // periodic off percent from gift
    /** @var float */
    public $periodsOffPercent;
    // currency to be payed in each period
    /** @var float */
    public $periodsPaymentAmount;
    // currency to be payed in total as periodic payments
    /** @var float */
    public $periodsTotalPayment;
    // periodics end date
    /** @var Carbon */
    public $periodsEnds;
    // periodics count
    /** @var int */
    public $periodsCount;
    // periodics days distance
    /** @var int */
    public $periodsDuration;

    protected $TYPE_CASTS = [
        'amount' => 'float',
        'fee' => 'float',
        'periodsAmount' => 'float',
        'quantity' => 'int',
        'offAmount' => 'float',
        'currencyPaid' => 'float',
        'virtualPaid' => 'float',
        'realSale' => 'float',
        'goodsSale' => 'float',
        'hasPeriods' => 'bool',
        'paidPeriods' => 'int',
        'periodsOffPercent' => 'float',
        'periodsPaymentAmount' => 'float',
        'periodsTotalPayment' => 'float',
        'periodsEnds' => 'carbon',
        'periodsCount' => 'int',
        'periodsDuration' => 'int'
    ];
}
