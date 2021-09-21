<?php

namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larapress\Profiles\IProfileUser;

/**
 * @property int             $id
 * @property int             $flags
 * @property int             $author_id
 * @property int             $stars
 * @property string          $mesage
 * @property array           $data
 * @property IProfileUser    $author
 * @property Product         $product
 * @property ProductReview   $review
 * @property \Carbon\Carbon  $created_at
 * @property \Carbon\Carbon  $updated_at
 * @property \Carbon\Carbon  $deleted_at
 */
class ProductReview extends Model
{
    use SoftDeletes;

    const FLAGS_PUBLIC = 1;

    protected $table = 'product_reviews';

    protected $fillable = [
        'author_id',
        'product_id',
        'review_id',
        'message',
        'flags',
        'stars',
        'data',
    ];

    protected $casts = [
        'data' => 'array'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(config('larapress.crud.user.model'), 'author_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
