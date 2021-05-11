<?php


namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int                $id
 * @property int                $flags
 * @property string             $type
 * @property array              $data
 * @property \Carbon\Carbon     $created_at
 * @property \Carbon\Carbon     $updated_at
 * @property \Carbon\Carbon     $deleted_at
 */
class BankGateway extends Model
{
    const FLAGS_DISABLED = 1;

    use SoftDeletes;

    protected $table = 'bank_gateways';

    protected $fillable = [
        'author_id',
        'type',
        'flags',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(config('larapress.crud.user.class'), 'author_id');
    }
}
