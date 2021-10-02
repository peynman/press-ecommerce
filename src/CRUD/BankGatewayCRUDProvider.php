<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\CRUD\Traits\CRUDProviderTrait;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\ICRUDUser;
use Larapress\CRUD\Services\CRUD\ICRUDVerb;
use Larapress\ECommerce\Models\BankGateway;
use Larapress\Profiles\IProfileUser;

class BankGatewayCRUDProvider implements ICRUDProvider
{
    use CRUDProviderTrait;

    public $name_in_config = 'larapress.ecommerce.routes.bank_gateways.name';
    public $model_in_config = 'larapress.ecommerce.routes.bank_gateways.model';
    public $compositions_in_config = 'larapress.ecommerce.routes.bank_gateways.compositions';

    public $verbs = [
        ICRUDVerb::VIEW,
        ICRUDVerb::SHOW,
        ICRUDVerb::CREATE,
        ICRUDVerb::EDIT,
        ICRUDVerb::DELETE,
    ];
    public $searchColumns = [
        'type',
        'data',
    ];
    public $validSortColumns = [
        'id',
        'author_id',
        'flags',
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
            'author' => config('larapress.crud.user.provider'),
        ];
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getCreateRules(Request $request): array
    {
        $rules = [
            'name' => 'required|string|unique:bank_gateways,name',
            'type' => 'required|string|in:'.(implode(",", array_keys(config('larapress.ecommerce.banking.ports')))),
            'flags' => 'nullable|numeric',
            'data' => 'nullable|object_json',
        ];

        return $rules;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function getUpdateRules(Request $request): array
    {
        $rules = $this->getCreateRules($request);
        $rules['name'] .= ','.$request->route('id');
        return $rules;
    }

    /**
     * @param array $args
     *
     * @return array
     */
    public function onBeforeCreate(array $args): array
    {
        /** @var ICRUDUser|IProfileUser $user */
        $user = Auth::user();

        $args['author_id'] = $user->id;

        return $args;
    }

    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function onBeforeQuery(Builder $query): Builder
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            $query->where('author_id', $user->id);
        }

        return $query;
    }

    /**
     * @param BankGateway $object
     *
     * @return bool
     */
    public function onBeforeAccess($object): bool
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-user'))) {
            return $object->author_id === $user->id;
        }

        return true;
    }
}
