<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Base\BaseCRUDProvider;
use Larapress\CRUD\Base\ICRUDProvider;
use Larapress\CRUD\Base\IPermissionsMetadata;
use Larapress\CRUD\ICRUDUser;
use Larapress\ECommerce\Models\BankGateway;
use Larapress\Profiles\IProfileUser;

class BankGatewayCRUDProvider implements ICRUDProvider, IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.bank_gateways.name';
    public $verbs = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
    ];
    public $model = BankGateway::class;
    public $createValidations = [
        'type' => 'required|string',
        'data' => 'nullable',
        'flags' => 'nullable|numeric',
    ];
    public $updateValidations = [
        'type' => 'required|string',
        'data' => 'nullable',
        'flags' => 'nullable|numeric',
    ];
    public $searchColumns = [
        'type',
        'data',
    ];
    public $validSortColumns = [
        'id',
        'author_id',
        'created_at',
        'updated_at',
        'flags',
    ];
    public $validRelations = [
        'author',
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
        /** @var ICRUDUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            $query->where('author_id', $user->id);
        }

        return $query;
    }

    /**
     * @param BankGateway $object
     *
     * @return bool
     */
    public function onBeforeAccess($object)
    {
        /** @var ICRUDUser|IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-user'))) {
            return $object->author_id === $user->id;
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
