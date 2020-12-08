<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Larapress\CRUD\Services\BaseCRUDProvider;
use Larapress\CRUD\Services\ICRUDProvider;
use Larapress\CRUD\Services\IPermissionsMetadata;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Azmoon\IAzmoonService;
use Larapress\Profiles\Services\FormEntry\IFormEntryService;

use Larapress\Reports\Services\IReportsService;

class ProductCRUDProvider implements ICRUDProvider, IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.products.name';
    public $verbs = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
        self::REPORTS,
    ];
    public $model = Product::class;
    public $createValidations = [
        'parent_id' => 'nullable|numeric|exists:products,id',
        'name' => 'required|string|unique:products,name',
        'group' => 'nullable|string',
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
        'group' => 'nullable|string',
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
        'children',
        'sales_real_amount',
        'sales_virtual_amount',
        'sales_fixed',
        'sales_periodic',
        'sales_periodic_payment',
    ];
    public $defaultShowRelations = [
        'types',
        'categories',
        'sales_real_amount',
        'sales_virtual_amount',
        'sales_fixed',
        'sales_periodic',
        'sales_periodic_payment',
    ];
    public $filterFields = [
        'types' => 'has:types',
        'categories' => 'has:categories',
        'parent_id' => 'equals:parent_id',
        'user_purchased_id' => 'has-has:purchased_carts:customer:id'
    ];
    public $filterDefaults = [];


    /**
     *
     */
    public function getReportSources()
    {
        /** @var IReportsService */
        $service = app(IReportsService::class);
        return [
        ];
    }

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
     * @param array $args
     * @return array
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

    public function onBeforeUpdate($args)
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
     * @param Product $object
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
     * @param Product $object
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

        if (isset($object->data['types']['azmoon']['file_id']) && !is_null($object->data['types']['azmoon']['file_id'])) {
            /** @var IAzmoonService */
            $service = app(IAzmoonService::class);
            $service->buildAzmoonDetails($object);
        }

        // remove ancestors cache
        if (!is_null($object->parent_id)) {
            Cache::tags(['product.ancestors:'.$object->id])->flush();
        }

        Cache::tags(['product:'.$object->id])->flush();
    }

    /**
     * @param Product $object
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

        if (isset($object->data['types']['azmoon']['file_id']) && !is_null($object->data['types']['azmoon']['file_id'])) {
            /** @var IAzmoonService */
            $service = app(IAzmoonService::class);
            $service->buildAzmoonDetails($object);
        }

        // remove ancestors cache
        if (!is_null($object->parent_id)) {
            Cache::tags(['product.ancestors:'.$object->id])->flush();
        }

        Cache::tags(['product:'.$object->id])->flush();
    }
}
