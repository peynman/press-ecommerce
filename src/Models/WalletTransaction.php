<?php


namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larapress\Profiles\Models\Domain;
use Larapress\ECommerce\IECommerceUser;

/**
 * @property int                    $id
 * @property int                    $user_id
 * @property int                    $domain_id
 * @property int                    $type
 * @property int                    $data
 * @property int                    $flags
 * @property int                    $currency
 * @property float                  $amount
 * @property IECommerceUser           $user
 * @property Domain                 $domain
 * @property \Carbon\Carbon      $expires_at
 * @property \Carbon\Carbon      $updated_at
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $deleted_at
 */
class WalletTransaction extends Model
{
    const TYPE_VIRTUAL_MONEY = 1;
    const TYPE_REAL_MONEY = 2;
    const TYPE_UNVERIFIED = 3;

    const FLAGS_REGISTRATION_GIFT = 1;
    const FLAGS_AUTO_IMPORT = 2;

    use SoftDeletes;

    protected $table = 'wallet_transactions';

    protected $fillable = [
        'user_id',
        'domain_id',
        'amount',
        'currency',
        'type',
        'data',
        'flags',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'data' => 'array'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('larapress.crud.user.class'), 'user_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }
}
