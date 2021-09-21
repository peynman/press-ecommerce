<?php

namespace Larapress\ECommerce\Services\Cart\Base;

class CartInstallmentPurchaseDetails extends CartInstallmentPaymentDetails {
    // product periodic currency price
    public $amount;
    // product paid amount in currency from cart
    public $currencyPaid;
    // virtual sale amount for this purchase
    public $virtualSale;
    // real sale amount for this purchase
    public $realSale;


    protected $TYPE_CASTS = [
        'custom' => 'bool',
        'index' => 'int',
        'total' => 'int',
        'originalCart' => 'int',
        'due_date' => 'carbon',
        'amount' => 'float',
        'currencyPaid' => 'float',
        'virtualSale' => 'float',
        'realSale' => 'float',
    ];
}
