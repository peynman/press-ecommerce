<?php

namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larapress\Profiles\Models\Domain;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Services\Cart\BaseCartTrait;
use Larapress\ECommerce\Services\Cart\ICart;
use Illuminate\Support\Collection;

/**
 * @property int            $id
 * @property int            $customer_id
 * @property int            $domain_id
 * @property int            $status
 * @property float          $amount
 * @property int            $currency
 * @property int            $flags
 * @property IECommerceUser   $customer
 * @property Domain         $domain
 * @property ICartItem[]|Collection    $products
 * @property array          $data
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class Cart extends Model implements ICart
{
    const STATUS_UNVERIFIED = 1;
    const STATUS_ACCESS_GRANTED = 2;
    const STATUS_ACCESS_COMPLETE = 3;

    const FLAGS_USER_CART = 1;
    const FLAGS_INCREASE_WALLET = 2;
    const FLAGS_EVALUATED = 4;
    const FLAGS_HAS_PERIODS = 8;
    const FLAGS_PERIOD_PAYMENT_CART = 16;
    const FLAGS_SYSTEM_API = 32;
    const FLAGS_ADMIN = 64;
    const FLAGS_PERIODIC_COMPLETED = 128;
    const FLGAS_FORWARDED_TO_BANK = 256;
    const FLGAS_SINGLE_INSTALLMENT = 512;

    use SoftDeletes;
    use BaseCartTrait;

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
        'amount' => 'float',
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
        return $this->belongsTo(config('larapress.crud.user.model'), 'customer_id');
    }

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function products()
    {
        return $this->belongsToMany(
            Product::class,
            'carts_products_pivot',
            'cart_id',
            'product_id'
        )
            ->using(CartItem::class)
            ->withPivot([
                'id',
                'data',
            ]);
    }

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function cart_items()
    {
        return $this->hasMany(
            CartItem::class,
            'cart_id',
            'id'
        );
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPaid()
    {
        return $this->status === self::STATUS_ACCESS_GRANTED || $this->status === self::STATUS_ACCESS_COMPLETE;
    }
}
