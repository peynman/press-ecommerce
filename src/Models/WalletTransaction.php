<?php


namespace Larapress\ECommerce\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larapress\Profiles\Models\Domain;

/**
 * @property int                    $id
 * @property int                    $user_id
 * @property int                    $domain_id
 * @property int                    $type
 * @property int                    $status
 * @property int                    $data
 * @property int                    $flags
 * @property int                    $currency
 * @property float                  $amount
 * @property IProfileUser           $user
 * @property Domain                 $domain
 * @property \Carbon\Carbon      $expires_at
 * @property \Carbon\Carbon      $updated_at
 * @property \Carbon\Carbon      $created_at
 * @property \Carbon\Carbon      $deleted_at
 */
class WalletTransaction extends Model
{
    const TYPE_MANUAL_MODIFY = 1;
    const TYPE_BANK_TRANSACTION = 2;

    const STATUS_UNVERIFIED = 1;
    const STATUS_ACCEPTED = 2;
    const STATUS_ON_HOLD = 3;
    const STATUS_VERIFIED = 4;

	use SoftDeletes;

	protected $table = 'wallet_transactions';

	protected $fillable = [
        'user_id',
        'domain_id',
        'amount',
        'currency',
        'type',
        'status',
        'data',
        'flags',
    ];

    protected $casts = [
        'data' => 'array'
    ];

	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function user() {
        return $this->belongsTo(config('larapress.crud.user.class'), 'user_id');
    }


	/**
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function domain() {
        return $this->belongsTo(Domain::class, 'domain_id');
    }
}