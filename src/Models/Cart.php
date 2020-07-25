<?php

namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\Domain;

/**
 * @property int            $id
 * @property int            $customer_id
 * @property int            $domain_id
 * @property int            $status
 * @property float          $amount
 * @property int            $currency
 * @property IProfileUser   $customer
 * @property Domain         $domain
 * @property ICartItem[]    $items
 * @property array          $data
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class Cart extends Model
{
    const STATUS_UNVERIFIED = 1;
    const STATUS_ACCESS_GRANTED = 2;
    const STATUS_ACCESS_COMPLETE = 3;

    const FLAG_USER_CART = 1;
    const FLAG_INCREASE_WALLET = 2;
    const FLAGS_EVALUATED = 4;
    const FLAGS_HAS_PERIODS = 8;

    use SoftDeletes;

    protected $table = 'carts';

    public $fillable = [
        'customer_id',
        'domain_id',
        'amount',
        'currency',
        'status',
        'flags',
        'data',
    ];

    public $casts = [
        'data' => 'array',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(config('larapress.crud.user.class'), 'customer_id');
    }

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products() {
        return $this->belongsToMany(
            Product::class,
            'carts_products_pivot',
            'cart_id',
            'product_id'
        );
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPaid() {
        return $this->status === self::STATUS_ACCESS_GRANTED || $this->status === self::STATUS_ACCESS_COMPLETE;
    }
}
