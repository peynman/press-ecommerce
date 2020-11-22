<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Larapress\CRUD\Services\BaseCRUDProvider;
use Larapress\CRUD\Services\ICRUDProvider;
use Larapress\CRUD\Services\IPermissionsMetadata;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\ProductCategory;
use Larapress\Pages\Models\Page;
use Larapress\Profiles\Services\FormEntry\IFormEntryService;

class ProductCategoryCRUDProvider implements ICRUDProvider, IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.product_categories.name';
    public $verbs = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
    ];
    public $model = ProductCategory::class;
    public $createValidations = [
        'parent_id' => 'nullable|numeric|exists:product_categories,id',
    	'name' => 'required|string|unique:products,name',
	    'data.title' => 'required',
	    'flags' => 'nullable|numeric',
    ];
    public $updateValidations = [
        'parent_id' => 'nullable|numeric|exists:product_categories,id',
    	'name' => 'required|string|unique:products,name',
	    'data.title' => 'required',
	    'flags' => 'nullable|numeric',
    ];
    public $autoSyncRelations = [];
    public $validSortColumns = [
        'id',
        'author_id',
        'created_at',
        'updated_at',
    ];
    public $searchColumns = [
        'id',
        'name',
        'data'
    ];
    public $validRelations = [
        'author'
    ];
    public $validFilters = [];
    public $defaultShowRelations = [];
    public $excludeIfNull = [];
    public $filterFields = [];
    public $filterDefaults = [];

    /**
     * Exclude current id in name unique request
     *
     * @param Request $request
     * @return void
     */
    public function getUpdateRules(Request $request) {
        $this->updateValidations['name'] .= ',' . $request->route('id');
        return $this->updateValidations;
    }

    /**
     * Undocumented function
     *
     * @param [type] $args
     * @return void
     */
    public function onBeforeCreate( $args )
    {
        $args['author_id'] = Auth::user()->id;

        /** @var IFormEntryService */
        $service = app(IFormEntryService::class);
        $data = $service->replaceBase64ImagesInInputs($args['data']);
        $args['data'] = $data;

        return $args;
    }

    /**
     * Undocumented function
     *
     * @param [type] $args
     * @return void
     */
    public function onBeforeUpdate( $args )
    {
        /** @var IFormEntryService */
        $service = app(IFormEntryService::class);
        $data = $service->replaceBase64ImagesInInputs($args['data']);

        $args['data'] = $data;

        return $args;
    }

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
     * @param Page $object
     *
     * @return bool
     */
    public function onBeforeAccess($object)
    {
        /** @var ICRUDUser|IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            return $user->id === $object->author_id;
        }

        return true;
    }
}
