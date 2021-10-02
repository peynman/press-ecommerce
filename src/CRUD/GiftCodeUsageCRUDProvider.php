<?php

namespace Larapress\ECommerce\CRUD;

use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDVerb;
use Larapress\CRUD\Services\CRUD\Traits\CRUDProviderTrait;

class GiftCodeUsageCRUDProvider implements ICRUDProvider
{
    use CRUDProviderTrait;

    public $name_in_config = 'larapress.ecommerce.routes.gift_code_usage.name';
    public $model_in_config = 'larapress.ecommerce.routes.gift_code_usage.model';
    public $compositions_in_config = 'larapress.ecommerce.routes.gift_code_usage.compositions';

    public $verbs = [
        ICRUDVerb::VIEW,
        ICRUDVerb::SHOW,
    ];

    public $validSortColumns = [
        'user_id',
        'code_id',
        'cart_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getValidRelations(): array
    {
        return [
            'user' => config('larapress.crud.user.provider'),
            'cart' => config('larapress.ecommerce.routes.carts.provider'),
            'gift_code' => config('larapress.ecommerce.routes.gift_codes.provider')
        ];
    }
}
