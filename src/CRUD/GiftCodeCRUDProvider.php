<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\BaseCRUDProvider;
use Larapress\CRUD\Services\ICRUDProvider;
use Larapress\CRUD\Services\IPermissionsMetadata;
use Larapress\CRUD\ICRUDUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\Domain;

class GiftCodeCRUDProvider implements ICRUDProvider, IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.gift_codes.name';
    public $verbs = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
    ];
    public $model = GiftCode::class;
    public function getCreateRules(Request $request)
    {
        return [
            'amount' => 'required|numeric',
            'currency' => 'required|numeric|in:'.implode(',', [config('larapress.ecommerce.banking.currency.id')]),
            'status' => 'required|numeric',
            'code' => 'required|string|min:6|regex:/(^[A-Za-z0-9-_.]+$)+/',
            'data.type' => 'required|string|in:percent,fixed',
        ];
    }

    public function getUpdateRules(Request $request)
    {
        return [
            'amount' => 'required|numeric',
            'currency' => 'required|numeric|in:'.implode(',', [config('larapress.ecommerce.banking.currency.id')]),
            'status' => 'required|numeric',
            'code' => 'required|string|min:6|regex:/(^[A-Za-z0-9-_.]+$)+/',
            'data.type' => 'required|string|in:percent,fixed',
        ];
    }

    public $searchColumns = [
        'code'
    ];
    public $validSortColumns = [
        'id',
        'author_id',
        'amount',
        'currency',
        'status',
        'flags',
    ];
    public $validRelations = [
        'author',
        'use_list',
        'use_list.user',
    ];
    public $defaultShowRelations = [
    ];

    /**
     * @param Builder $query
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function onBeforeQuery($query)
    {
        /** @var IProfileUser|ICRUDUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            $query->where('author_id', $user->id);
        }

        return $query;
    }

    /**
     * @param Domain $object
     *
     * @return bool
     */
    public function onBeforeAccess($object)
    {
        /** @var ICRUDUser|IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            return $object->author_id == $user->id;
        }

        return true;
    }

    /**
     * @param array $args
     *
     * @return array
     */
    public function onBeforeCreate($args)
    {
        /** @var ICRUDUser|IProfileUser $user */
        $user = Auth::user();
        $args['author_id'] = $user->id;
        return $args;
    }
}
