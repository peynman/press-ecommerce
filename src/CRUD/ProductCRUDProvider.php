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
use Larapress\ECommerce\Services\Product\ProductReports;
use Larapress\Profiles\Models\FormEntry;
use Larapress\Profiles\Services\FormEntry\IFormEntryService;
use Larapress\ECommerce\IECommerceUser;
use Larapress\Reports\Services\IMetricsService;
use Larapress\Reports\Services\IReportsService;

class ProductCRUDProvider implements
    ICRUDProvider,
    IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.products.name';
    public $verbs = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
        self::REPORTS,
        'sales',
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
    public $searchColumns = [
        'name',
        'id',
        'data',
    ];
    public $defaultShowRelations = [
        'types',
        'categories',
    ];
    public $filterFields = [
        'relations' => [
            'sales_real_amount' => [
                'sales_from' => 'after:created_at',
                'sales_to' => 'before:created_at',
            ],
            'sales_virtual_amount' => [
                'sales_from' => 'after:created_at',
                'sales_to' => 'before:created_at',
            ],
            'sales_fixed' => [
                'sales_from' => 'after:created_at',
                'sales_to' => 'before:created_at',
            ],
            'sales_periodic' => [
                'sales_from' => 'after:created_at',
                'sales_to' => 'before:created_at',
            ],
            'sales_role_support_amount' => [
                'sales_from' => 'after:created_at',
                'sales_to' => 'before:created_at',
            ],
            'sales_role_support_ext_amount' => [
                'sales_from' => 'after:created_at',
                'sales_to' => 'before:created_at',
            ]
        ],
        'types' => 'has:types',
        'categories' => 'has:categories',
        'parent_id' => 'equals:parent_id',
        'user_purchased_id' => 'has-has:purchased_carts:customer:id'
    ];
    public $filterDefaults = [];

    public function getValidRelations()
    {
        return [
            'author' => function($user) {
                return $user->hasPermission(config('larapress.profiles.routes.users.name').'.view');
            },
            'types' => function($user) {
                return true;
            },
            'categories' => function($user) {
                return true;
            },
            'parent' => function($user) {
                return true;
            },
            'children' => function($user) {
                return true;
            },
            'sales_real_amount' => function($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.products.name').'.sales');
            },
            'sales_virtual_amount' => function($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.products.name').'.sales');
            },
            'sales_fixed' => function($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.products.name').'.sales');
            },
            'sales_periodic' => function($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.products.name').'.sales');
            },
            'sales_periodic_payment' => function($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.products.name').'.sales');
            },
            'sales_role_support_amount' => function($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.products.name').'.sales');
            },
            'sales_role_support_ext_amount' => function($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.products.name').'.sales');
            },
            'remaining_periodic_count' => function($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.products.name').'.sales');
            },
            'remaining_periodic_amount' => function($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.products.name').'.sales');
            },
        ];
    }

    public function getValidSortColumns()
    {
        return [
            'id' => 'id',
            'name' => 'name',
            'publish_at' => 'publish_at',
            'expires_at' => 'expires_at',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'starts_at' => function ($query, $dir) {
                $query->orderBy('data->types->session->start_at', $dir);
            },
        ];
    }

    /**
     *
     */
    public function getReportSources()
    {
        return [
            new ProductReports(app(IReportsService::class), app(IMetricsService::class))
        ];
    }

    /**
     * Exclude current id in name unique request
     *
     * @param Request $request
     * @return void
     */
    public function getUpdateRules(Request $request)
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
    public function onBeforeCreate($args)
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
        /** @var IECommerceUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            if ($user->hasRole(config('larapress.ecommerce.lms.owner_role_id'))) {
                $query->whereIn('id', $user->getOwenedProductsIds());
            } else {
//                $query->where('author_id', $user->id);
            }
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
        /** @var IECommerceUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            if ($user->hasRole(config('larapress.ecommerce.lms.owner_role_id'))) {
                return in_array($object->id, $user->getOwenedProductsIds());
            } else {
                return $user->id === $object->author_id;
            }
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
            Cache::tags(['product.ancestors:' . $object->id])->flush();
        }

        Cache::tags(['product:' . $object->id])->flush();
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
            Cache::tags(['product.ancestors:' . $object->id])->flush();
        }

        Cache::tags(['product:' . $object->id])->flush();
    }
}
