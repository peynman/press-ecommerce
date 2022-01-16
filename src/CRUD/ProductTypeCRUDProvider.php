<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\CRUD\Traits\CRUDProviderTrait;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDVerb;
use Larapress\CRUD\Services\RBAC\IPermissionsMetadata;
use Larapress\ECommerce\Models\ProductType;

class ProductTypeCRUDProvider implements ICRUDProvider
{
    use CRUDProviderTrait;

    public $name_in_config = 'larapress.ecommerce.routes.product_types.name';
    public $model_in_config = 'larapress.ecommerce.routes.product_types.model';
    public $compositions_in_config = 'larapress.ecommerce.routes.product_types.compositions';

    public $verbs = [
        ICRUDVerb::VIEW,
        ICRUDVerb::SHOW,
        ICRUDVerb::CREATE,
        ICRUDVerb::EDIT,
        ICRUDVerb::DELETE,
    ];
    public $createValidations = [
        'name' => 'required|string|unique:product_types,name',
        'data.title' => 'required',
        'flags' => 'nullable|numeric',
    ];
    public $updateValidations = [
        'name' => 'required|string|unique:product_types,name',
        'data.title' => 'required',
        'flags' => 'nullable|numeric',
    ];
    public $validSortColumns = [
        'id',
        'name',
        'author_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    public $searchColumns = [
        'id',
        'name',
        'data'
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
     * Exclude current id in name unique request
     *
     * @param Request $request
     *
     * @return array
     */
    public function getUpdateRules(Request $request): array
    {
        $this->updateValidations['name'] .= ',' . $request->route('id') . ',id,deleted_at,NULL';
        return $this->updateValidations;
    }

    /**
     * Undocumented function
     *
     * @param array $args
     *
     * @return void
     */
    public function onBeforeCreate(array $args): array
    {
        $args['author_id'] = Auth::user()->id;

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
     * @param ProductType $object
     *
     * @return bool
     */
    public function onBeforeAccess($object): bool
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            return $user->id === $object->author_id;
        }

        return true;
    }
}
