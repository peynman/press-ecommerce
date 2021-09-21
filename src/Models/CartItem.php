<?php

namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $cart_id
 * @property int $product_id
 * @property array $data
 */
class CartItem extends Pivot
{
    protected $table = 'carts_products_pivot';

    public $incrementing = true;
    protected $primaryKey = 'id';

    public $fillable = [
        'cart_id',
        'product_id',
        'data'
    ];

    protected $casts = [
        'data' => 'array'
    ];
}
