<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Extend\Helpers;
use Larapress\CRUD\Services\CRUD\Traits\CRUDProviderTrait;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDVerb;
use Larapress\CRUD\Services\CRUD\Traits\CRUDRelationSyncTrait;
use Larapress\CRUD\Services\RBAC\IPermissionsMetadata;
use Larapress\ECommerce\Controllers\ProductController;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Services\Product\Reports\ProductSalesReports;
use Larapress\FileShare\Services\FileUpload\IFileUploadService;

class ProductCRUDProvider implements
    ICRUDProvider,
    IPermissionsMetadata
{
    use CRUDProviderTrait;
    use CRUDRelationSyncTrait;

    public $name_in_config = 'larapress.ecommerce.routes.products.name';
    public $model_in_config = 'larapress.ecommerce.routes.products.model';
    public $compositions_in_config = 'larapress.ecommerce.routes.products.compositions';

    public $validSortColumns = [
        'id' => 'id',
        'name' => 'name',
        'publish_at' => 'publish_at',
        'expires_at' => 'expires_at',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
        'deleted_at' => 'deleted_at',
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

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getPermissionVerbs(): array
    {
        return [
            ICRUDVerb::VIEW,
            ICRUDVerb::SHOW,
            ICRUDVerb::CREATE,
            ICRUDVerb::EDIT,
            ICRUDVerb::DELETE,
            ICRUDVerb::CREATE . '.duplicate' => [
                'methods' => ['POST'],
                'uses' => '\\' . ProductController::class . '@duplicateProduct',
                'url' => config('larapress.ecommerce.routes.gift_codes.name') . '/clone',
            ]
        ];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getReportSources(): array
    {
        return [
            ProductSalesReports::NAME => ProductSalesReports::class,
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
        $createValidations = $this->getCreateRules($request);
        $createValidations['name'] .= ',' . $request->route('id');
        return $createValidations;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     *
     * @return array
     */
    public function getCreateRules(Request $request): array
    {
        $avCurrencyIds = collect(config('larapress.ecommerce.banking.available_currencies'))->pluck('id');

        return [
            'name' => 'required|string|unique:products,name',
            'data.title' => 'required',

            'parent_id' => 'nullable|numeric|exists:products,id',

            'priority' => 'nullable|numeric',
            'group' => 'nullable|string',
            'flags' => 'nullable|numeric',

            'publish_at' => 'nullable|datetime_zoned',
            'expires_at' => 'nullable|datetime_zoned',

            'data.quantized' => 'nullable|in:true,false,1,0',
            'data.maxQuantity' => 'required_if:data.quantized,true|numeric',

            'data.fixedPrice.amount' => 'nullable|numeric',
            'data.fixedPrice.currency' => 'required_with:data.fixedPrice.amount|numeric|in:' . $avCurrencyIds->implode(','),
            'data.fixedPrice.offAmount' => 'nullable|numeric',

            'data.periodicPrice.*' => 'nullable',
            'data.periodicPrice.amount' => 'nullable|numeric',
            'data.periodicPrice.currency' => 'required_with:data.periodicPrice.amount|numeric|in:' . $avCurrencyIds->implode(','),
            'data.periodicPrice.offAmount' => 'nullable|numeric',
            'data.periodicPrice.endsAt' => 'nullable|datetime_zoned',
            'data.periodicPrice.periodCount' => 'required_with:data.periodicPrice.amount|numeric',
            'data.periodicPrice.periodDuration' => 'required_with:data.periodicPrice.amount|numeric',
            'data.periodicPrice.periodAmount' => 'required_with:data.periodicPrice.amount|numeric',

            'types.*' => 'nullable|exists:product_types,id',
            'categories.*' => 'nullable|exists:product_categories,id',
        ];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getFilterFields(): array
    {
        return [
            'types' => 'has:types',
            'categories' => 'has:categories',
            'parent_id' => 'equals:parent_id',
            'user_purchased_id' => 'has-has:purchased_carts:customer:id'
        ];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getValidRelations(): array
    {
        return [
            'author' => config('larapress.crud.user.provider'),
            'types' => config('larapress.ecommerce.routes.product_types.provider'),
            'categories' => config('larapress.ecommerce.routes.product_categories.provider'),
            'parent' => function ($user) {
                return true;
            },
            'children' => function ($user) {
                return true;
            },
        ];
    }

    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function onBeforeQuery(Builder $query): Builder
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            if ($user->hasRole(config('larapress.ecommerce.products.product_owner_role_ids'))) {
                $query->orWhereIn('id', $user->getOwenedProductsIds());
            } else {
                $query->orWhere('author_id', $user->id);
            }
        }

        return $query;
    }

    /**
     * @param Product $object
     *
     * @return bool
     */
    public function onBeforeAccess($object): bool
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            if ($user->hasRole(config('larapress.ecommerce.products.product_owner_role_ids'))) {
                return in_array($object->id, $user->getOwenedProductsIds());
            }
            return $user->id === $object->author_id;
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @param array $args
     *
     * @return array
     */
    public function onBeforeCreate(array $args): array
    {
        $args['author_id'] = Auth::user()->id;

        /** @var IFileUploadService */
        $service = app(IFileUploadService::class);
        $data = $service->replaceBase64WithFilePathValuesRecursuve($args['data'], null, 'public', 'product-images');

        // make sure types json object is stored as object and not array
        if (isset($data['types'])) {
            foreach ($data['types'] as $type => $meta) {
                $data['types'][$type] = array_merge($meta, ['obj' => true]);
            }
        }

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
        $data = $service->replaceBase64WithFilePathValuesRecursuve($args['data'], null, 'public', 'product-images');

        // make sure types json object is stored as object and not array
        if (isset($data['types'])) {
            foreach ($data['types'] as $type => $meta) {
                $data['types'][$type] = array_merge($meta, ['obj' => true]);
            }
        }

        $args['data'] = $data;

        return $args;
    }

    /**
     * @param Product $object
     * @param array  $input_data
     *
     * @return void
     */
    public function onAfterCreate($object, array $input_data): void
    {
        // sycn types
        $this->syncBelongsToManyRelation('types', $object, $input_data);

        // sync categorires
        if (isset($input_data['categories'])) {
            $this->syncBelongsToManyRelation('categories', $object, $input_data);
        }

        // remove ancestors cache
        if (!is_null($object->parent_id)) {
            Helpers::forgetCachedValues(['product.ancestors:' . $object->id]);
        }

        Helpers::forgetCachedValues(['product:' . $object->id]);
    }

    /**
     * @param Product $object
     * @param array $input_data
     *
     * @return void
     */
    public function onAfterUpdate($object, array $input_data): void
    {
        // sycn types
        $this->syncBelongsToManyRelation('types', $object, $input_data);

        // sync categorires
        if (isset($input_data['categories'])) {
            $this->syncBelongsToManyRelation('categories', $object, $input_data);
        }

        // remove ancestors cache
        if (!is_null($object->parent_id)) {
            Helpers::forgetCachedValues(['product.ancestors:' . $object->id]);
        }

        Helpers::forgetCachedValues(['product:' . $object->id]);
    }
}
