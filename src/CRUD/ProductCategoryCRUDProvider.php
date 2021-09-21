<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\CRUD\Traits\CRUDProviderTrait;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDVerb;
use Larapress\ECommerce\Models\ProductCategory;
use Larapress\FileShare\Services\FileUpload\IFileUploadService;
use Larapress\Profiles\Services\FormEntry\IFormEntryService;

class ProductCategoryCRUDProvider implements ICRUDProvider
{
    use CRUDProviderTrait;

    public $name_in_config = 'larapress.ecommerce.routes.product_categories.name';
    public $model_in_config = 'larapress.ecommerce.routes.product_categories.model';
    public $compositions_in_config = 'larapress.ecommerce.routes.product_categories.compositions';

    public $verbs = [
        ICRUDVerb::VIEW,
        ICRUDVerb::CREATE,
        ICRUDVerb::EDIT,
        ICRUDVerb::DELETE,
    ];
    public $createValidations = [
        'parent_id' => 'nullable|numeric|exists:product_categories,id',
        'name' => 'required|string|unique:product_categories,name',
        'data.title' => 'required',
        'flags' => 'nullable|numeric',
    ];
    public $updateValidations = [
        'parent_id' => 'nullable|numeric|exists:product_categories,id',
        'name' => 'required|string|unique:product_categories,name',
        'data.title' => 'required',
        'flags' => 'nullable|numeric',
    ];
    public $validSortColumns = [
        'id',
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
            'author' => config('larapress.crud.user.provider')
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
        $this->updateValidations['name'] .= ',' . $request->route('id');
        return $this->updateValidations;
    }

    /**
     * Undocumented function
     *
     * @param array $args
     * @return array
     */
    public function onBeforeCreate(array $args): array
    {
        $args['author_id'] = Auth::user()->id;

        /** @var IFileUploadService */
        $service = app(IFileUploadService::class);
        $data = $service->replaceBase64WithFilePathValuesRecursuve($args['data'], null);
        $args['data'] = $data;

        return $args;
    }

    /**
     * Undocumented function
     *
     * @param array $args
     *
     * @return array
     */
    public function onBeforeUpdate(array $args): array
    {
        /** @var IFileUploadService */
        $service = app(IFileUploadService::class);
        $data = $service->replaceBase64WithFilePathValuesRecursuve($args['data'], null);

        $args['data'] = $data;

        return $args;
    }

    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function onBeforeQuery(Builder $query): Builder
    {
        /** @var ICRUDUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            $query->where('author_id', $user->id);
        }

        return $query;
    }

    /**
     * @param ProductCategory $object
     *
     * @return bool
     */
    public function onBeforeAccess($object): bool
    {
        /** @var ICRUDUser|IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            return $user->id === $object->author_id;
        }

        return true;
    }
}
