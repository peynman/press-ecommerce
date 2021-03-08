<?php

namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int            $cart_id
 * @property int            $product_id
 */
class CartItem extends Model
{
    protected $table = 'carts_products_pivot';

    public $fillable = [
        'cart_id',
        'product_id',
    ];
}
