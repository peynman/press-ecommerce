<?php

namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int            $id
 * @property string         $name
 * @property string         $title
 * @property int            $flags
 * @property int            $status
 * @property array          $schema
 * @property Product[]      $products
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class ProductType extends Model
{
    use SoftDeletes;

    protected $table = 'product_types';

    protected $fillable = [
        'author_id',
    	'name',
	    'data',
	    'flags',
    ];

    protected $casts = [
    	'data' => 'array'
    ];

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function products() {
    	return $this->belongsToMany(
    		Product::class,
		    'product_type_pivot',
		    'product_type_id',
		    'product_id'
	    );
    }

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function author() {
        return $this->belongsTo(config('larapress.crud.user.class'), 'author_id');
    }
}
