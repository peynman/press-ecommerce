<?php

namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larapress\Profiles\IProfileUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Larapress\ECommerce\Factories\GiftCodeFactory;

/**
 * @property int            $id
 * @property int            $author_id
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
    use HasFactory;

    const FLAGS_EXPIRED = 2;

    use SoftDeletes;

    protected $table = 'gift_codes';

    public $fillable = [
        'author_id',
        'code',
        'amount',
        'currency',
        'flags',
        'data',
    ];

    public $casts = [
        'data' => 'array',
    ];

    /**
     * Undocumented function
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return GiftCodeFactory::new();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(config('larapress.crud.user.model'), 'author_id');
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
}
