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
 * @property string          $description
 * @property array           $data
 * @property IProfileUser    $author
 * @property Product         $product
 * @property \Carbon\Carbon  $created_at
 * @property \Carbon\Carbon  $updated_at
 * @property \Carbon\Carbon  $deleted_at
 */
class ProductReview extends Model
{
    use SoftDeletes;

    protected $table = 'product_categories';

    protected $fillable = [
        'author_id',
        'product_id',
        'description',
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
        return $this->belongsTo(config('larapress.crud.user.class'), 'author_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
