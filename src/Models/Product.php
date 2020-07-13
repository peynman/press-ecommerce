<?php

namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\ECommerce\Services\Banking\ICartItem;
use Larapress\Profiles\IProfileUser;

/**
 * @property int                  $id
 * @property string               $name
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

    protected $table = 'products';

    protected $fillable = [
    	'id', // fill from data
	    'author_id',
	    'parent_id',
    	'name',
	    'data',
        'flags',
        'priority',
	    'publish_at',
	    'expires_at',
    ];

    public $dates = [
    ];

    public $casts = [
    	'options' => 'array',
	    'data' => 'array',
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
	public function types() {
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
	public function categories() {
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
	public function parent() {
		return $this->belongsTo(Product::class, 'parent_id');
	}

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function children() {
		return $this->hasMany(Product::class,'parent_id');
    }

        /**
     * Undocumented function
     *
     * @return float
     */
    public function price() {
        if (!isset($this->data['pricing'])) {
            return $this->pricePeriodic();
        }

        if (!is_null($this->data['pricing']) && count($this->data['pricing']) > 0) {
            $prices = $this->data['pricing'];
            $prior = $prices[0];
            if (!isset($prior['priority'])) {
                $prior['priority'] = 0;
            }
            foreach ($prices as $price) {
                if (isset($price['priority'])) {
                    if ($price['priority'] > $prior['priority']) {
                        $prior = $price;
                    }
                }
            }

            return $prior['amount'];
        }

        return 0;
    }


    /**
     * Undocumented function
     *
     * @return float
     */
    public function pricePeriodic() {
        if (!isset($this->data['price_periodic'])) {
            return 0;
        }

        if (!is_null($this->data['price_periodic']) && count($this->data['price_periodic']) > 0) {
            $prices = $this->data['price_periodic'];
            $prior = $prices[0];
            if (!isset($prior['priority'])) {
                $prior['priority'] = 0;
            }
            foreach ($prices as $price) {
                if (isset($price['priority'])) {
                    if ($price['priority'] > $prior['priority']) {
                        $prior = $price;
                    }
                }
            }

            return $prior['amount'];
        }

        return 0;
    }

        /**
     * Undocumented function
     *
     * @return float
     */
    public function pricePeriods() {

    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function currency() {
        return 1;
    }

    /**
     * Undocumented function
     *
     * @return String
     */
    public function product_uid() {
        return 'product:'.$this->id;
    }

    /**
     * Undocumented function
     *
     * @return Model
     */
    public function model() {
        return $this;
    }
}
