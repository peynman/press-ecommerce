<?php

namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\Domain;

/**
 * @property int            $id
 * @property int            $author_id
 * @property int            $status
 * @property int            $flags
 * @property float          $amount
 * @property int            $currency
 * @property string         $code
 * @property IProfileUser   $author
 * @property array          $data
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class GiftCode extends Model
{
    const STATUS_AVAILABLE = 1;
    const STATUS_EXPIRED = 2;

    use SoftDeletes;

    protected $table = 'gift_codes';

    public $fillable = [
        'author_id',
        'code',
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
    public function author()
    {
        return $this->belongsTo(config('larapress.crud.user.class'), 'author_id');
    }


    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function use_list()
    {
        return $this->hasMany(GiftCodeUse::class, 'code_id');
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isPercentGift()
    {
        return $this->data['type'] === 'percent';
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function isFixedGift()
    {
        return $this->data['type'] === 'fixed';
    }
}
