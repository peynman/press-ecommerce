<?php


namespace Larapress\ECommerce\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\Factory;
use Larapress\ECommerce\Factories\BankGatewayFactory;

/**
 * @property int                $id
 * @property int                $flags
 * @property string             $name
 * @property string             $type
 * @property array              $data
 * @property \Carbon\Carbon     $created_at
 * @property \Carbon\Carbon     $updated_at
 * @property \Carbon\Carbon     $deleted_at
 */
class BankGateway extends Model
{
    const FLAGS_DISABLED = 1;

    use HasFactory;
    use SoftDeletes;

    protected $table = 'bank_gateways';

    protected $fillable = [
        'author_id',
        'name',
        'type',
        'flags',
        'data',
    ];

    protected $casts = [
        'data' => 'array',
    ];


    /**
     * Undocumented function
     *
     * @return Factory
     */
    protected static function newFactory()
    {
        return BankGatewayFactory::new();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function author()
    {
        return $this->belongsTo(config('larapress.crud.user.model'), 'author_id');
    }

}
