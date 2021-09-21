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
class GiftCodeUse extends Model
{
    use SoftDeletes;

    protected $table = 'gift_codes_use';

    public $fillable = [
        'user_id',
        'code_id',
        'cart_id',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('larapress.crud.user.model'), 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function gift_code()
    {
        return $this->belongsTo(GiftCode::class, 'code_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }
}
