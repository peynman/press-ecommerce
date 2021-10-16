<?php

namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Larapress\Profiles\Models\Domain;
use Larapress\ECommerce\IECommerceUser;

/**
 * @property int            $id
 * @property float          $amount
 * @property int            $currency
 * @property int            $bank_gateway_id
 * @property int            $cart_id
 * @property int            $domain_id
 * @property int            $customer_id
 * @property string         $tracking_code
 * @property string         $reference_code
 * @property int            $status
 * @property int            $flags
 * @property string         $ip_address
 * @property array          $data
 * @property BankGateway    $bank_gateway
 * @property Cart           $cart
 * @property Domain         $domain
 * @property IECommerceUser $customer
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class BankGatewayTransaction extends Model
{
    const STATUS_CREATED = 1;
    const STATUS_FORWARDED = 2;
    const STATUS_RECEIVED = 3;
    const STATUS_CANCELED = 4;
    const STATUS_FAILED = 5;
    const STATUS_SUCCESS = 6;

    use SoftDeletes;

    protected $table = 'bank_gateway_transactions';

    protected $fillable = [
        'bank_gateway_id',
        'domain_id',
        'customer_id',
        'cart_id',
        'agent_ip',
        'agent_client',
        'amount',
        'currency',
        'tracking_code',
        'reference_code',
        'status',
        'flags',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
        'amount' => 'float',
    ];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function bank_gateway()
    {
        return $this->belongsTo(BankGateway::class, 'bank_gateway_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(config('larapress.crud.user.model'), 'customer_id');
    }
}
