<?php

namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larapress\Profiles\IProfileUser;

/**
 * @property int             $id
 * @property string          $name
 * @property int             $flags
 * @property int             $parent_id
 * @property int             $author_id
 * @property array           $data
 * @property Product[]       $products
 * @property ProductType[]   $product_types
 * @property IProfileUser    $author
 * @property \Carbon\Carbon  $created_at
 * @property \Carbon\Carbon  $updated_at
 * @property \Carbon\Carbon  $deleted_at
 * @property ProductCategory    $parent
 * @property ProductCategory[]  $children
 */
class ProductCategory extends Model
{
    use SoftDeletes;

    protected $table = 'product_categories';

    protected $fillable = [
	    'author_id',
	    'parent_id',
    	'name',
	    'flags',
	    'data',
    ];

    protected $casts = [
    	'data' => 'array'
    ];

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function author() {
        return $this->belongsTo(config('larapress.crud.user.class'), 'author_id');
    }

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function product_types() {
    	return $this->belongsToMany(
    		ProductType::class,
		    'product_category_type_pivot',
		    'product_category_id',
		    'product_type_id'
	    );
    }

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function products() {
    	return $this->belongsToMany(
    		Product::class,
		    'product_category_pivot',
		    'product_category_id',
		    'product_id'
	    );
    }

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function parent() {
		return $this->belongsTo(ProductCategory::class, 'parent_id');
    }

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function children() {
		return $this->hasMany(ProductCategory::class, 'parent_id');
    }
}
