<?php

namespace Larapress\ECommerce\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larapress\ECommerce\Services\Banking\ICartItem;
use Larapress\ECommerce\Services\Product\ProductSalesAmountRelationship;
use Larapress\ECommerce\Services\Product\ProductSalesCountRelationship;
use Larapress\Profiles\IProfileUser;

/**
 * @property int                  $id
 * @property string               $name
 * @property string               $group
 * @property int                  $flags
 * @property int                  $priority
 * @property int                  $parent_id
 * @property int                  $author_id
 * @property IProfileUser         $author
 * @property ProductCategory[]    $categories
 * @property Product[]            $children
 * @property Product              $parent
 * @property ProductType[]        $types
 * @property array                $data
 * @property \Carbon\Carbon       $publish_at
 * @property \Carbon\Carbon       $expires_at
 * @property \Carbon\Carbon       $created_at
 * @property \Carbon\Carbon       $updated_at
 * @property \Carbon\Carbon       $deleted_at
 */
class Product extends Model implements ICartItem
{
    use SoftDeletes;
    use ProductCartItem;

    protected $table = 'products';

    protected $fillable = [
        'id', // fill from data
        'author_id',
        'parent_id',
        'name',
        'group',
        'data',
        'flags',
        'priority',
        'publish_at',
        'expires_at',
    ];

    public $dates = [
        'publish_at',
        'expires_at',
    ];

    public $casts = [
        'options' => 'array',
        'data' => 'array',
    ];

    public $appends = [
        'price-tag'
    ];

    public $hidden = [
        'sales'
    ];
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(config('larapress.crud.user.class'), 'author_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function types()
    {
        return $this->belongsToMany(
            ProductType::class,
            'product_type_pivot',
            'product_id',
            'product_type_id'
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function categories()
    {
        return $this->belongsToMany(
            ProductCategory::class,
            'product_category_pivot',
            'product_id',
            'product_category_id'
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo(Product::class, 'parent_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function children()
    {
        return $this->hasMany(Product::class, 'parent_id');
    }

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function carts()
    {
        return $this->belongsToMany(
            Cart::class,
            'carts_products_pivot',
            'product_id',
            'cart_id'
        );
    }


    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function purchased_carts()
    {
        return $this->carts()->whereIn('status', [Cart::STATUS_ACCESS_GRANTED, Cart::STATUS_ACCESS_COMPLETE]);
    }

    /**
     * Undocumented function
     *
     * @return ProductSalesAmountRelationship
     */
    public function sales_real_amount()
    {
        return new ProductSalesAmountRelationship($this, WalletTransaction::TYPE_REAL_MONEY);
    }

    /**
     * Undocumented function
     *
     * @return ProductSalesAmountRelationship
     */
    public function sales_virtual_amount()
    {
        return new ProductSalesAmountRelationship($this, WalletTransaction::TYPE_VIRTUAL_MONEY);
    }

    /**
     * Undocumented function
     *
     * @return ProductSalesCountRelationship
     */
    public function sales_fixed() {
        return new ProductSalesCountRelationship($this, 'sales_fixed');
    }

    /**
     * Undocumented function
     *
     * @return ProductSalesCountRelationship
     */
    public function sales_periodic() {
        return new ProductSalesCountRelationship($this, 'sales_periodic');
    }

    /**
     * Undocumented function
     *
     * @return ProductSalesCountRelationship
     */
    public function sales_periodic_payment() {
        return new ProductSalesCountRelationship($this, 'periodic_payment');
    }

    /**
     * Undocumented function
     *
     * @param string $value
     * @return void
     */
    public function setExpiresAtAttribute($value)
    {
        $this->attributes['expires_at'] =  Carbon::parse($value);
    }

    /**
     * Undocumented function
     *
     * @param string $value
     * @return void
     */
    public function setPublishAtAttribute($value)
    {
        $this->attributes['publish_at'] =  Carbon::parse($value);
    }
}
