<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Larapress\CRUD\Base\BaseCRUDProvider;
use Larapress\CRUD\Base\ICRUDProvider;
use Larapress\CRUD\Base\IPermissionsMetadata;
use Larapress\ECommerce\Models\Product;
use Larapress\Pages\Models\Page;

class ProductCRUDProvider implements ICRUDProvider, IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.products.name';
    public $verbs = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
    ];
    public $model = Product::class;
    public $createValidations = [
        'parent_id' => 'nullable|numeric|exists:products,id',
    	'name' => 'required|string|unique:products,name',
	    'data.title' => 'required',
        'priority' => 'nullable|numeric',
	    'flags' => 'nullable|numeric',
	    'publish_at' => 'nullable|datetime_zoned',
        'expires_at' => 'nullable|datetime_zoned',
        'types' => 'required|array|min:1',
        'types.*.id' => 'required|exists:product_types,id',
        'categories.*.id' => 'nullable|exists:product_categories,id',
    ];
    public $updateValidations = [
        'parent_id' => 'nullable|numeric|exists:products,id',
        'name' => 'required|string|unique:products,name',
        'priority' => 'nullable|numeric',
	    'data.title' => 'required',
	    'flags' => 'nullable|numeric',
	    'publish_at' => 'nullable|datetime_zoned',
	    'expires_at' => 'nullable|datetime_zoned',
        'types' => 'nullable|array|min:1',
        'types.*.id' => 'nullable|exists:product_types,id',
        'categories.*.id' => 'nullable|exists:product_categories,id',
    ];
    public $autoSyncRelations = [];
    public $validSortColumns = [
        'id',
        'name',
        'publish_at',
        'expires_at'
    ];
    public $searchColumns = [
        'name',
        'id',
        'data',
    ];
    public $validRelations = [
        'author',
        'types',
        'categories',
        'parent',
        'children'
    ];
    public $validFilters = [];
    public $defaultShowRelations = [
        'types',
        'categories'
    ];
    public $excludeFromUpdate = [];
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

    /**
     * @param Domain $object
     * @param array  $input_data
     *
     * @return array|void
     */
    public function onAfterCreate($object, $input_data)
    {
        $this->syncBelongsToManyRelation('types', $object, $input_data);
        if (isset($input_data['categories'])) {
            $this->syncBelongsToManyRelation('categories', $object, $input_data);
        }
    }

    /**
     * @param Domain $object
     * @param array $input_data
     *
     * @return array|void
     */
    public function onAfterUpdate($object, $input_data)
    {
        $this->syncBelongsToManyRelation('types', $object, $input_data);
        if (isset($input_data['categories'])) {
            $this->syncBelongsToManyRelation('categories', $object, $input_data);
        }
    }
}
