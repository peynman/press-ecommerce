<?php

namespace Larapress\ECommerce\CRUD;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Larapress\CRUD\BaseFlags;
use Larapress\CRUD\Extend\Helpers;
use Larapress\CRUD\Services\CRUD\Traits\CRUDProviderTrait;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDVerb;
use Larapress\CRUD\Services\RBAC\IPermissionsMetadata;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Cart\CartEvent;
use Larapress\ECommerce\Services\Cart\ICartService;
use Larapress\ECommerce\Services\Cart\Reports\CartReport;
use Larapress\Profiles\IProfileUser;
use Larapress\Reports\Models\MetricCounter;

class CartCRUDProvider implements
    ICRUDProvider,
    IPermissionsMetadata
{
    use CRUDProviderTrait;

    public $name_in_config = 'larapress.ecommerce.routes.carts.name';
    public $model_in_config = 'larapress.ecommerce.routes.carts.model';
    public $compositions_in_config = 'larapress.ecommerce.routes.carts.compositions';

    public $verbs = [
        ICRUDVerb::VIEW,
        ICRUDVerb::SHOW,
        ICRUDVerb::CREATE,
        ICRUDVerb::EDIT,
        ICRUDVerb::DELETE,
    ];
    public $createValidations = [
        'customer_id' => 'required|numeric|exists:users,id',
        'amount' => 'required|numeric',
        'currency' => 'required|numeric|exists:filters,id',
        'status' => 'required|numeric',
        'flags' => 'nullable|numeric',
        'products.*.id' => 'required|numeric|exists:products,id',
        'extra_product_id' => 'nullable|numeric|exists:products,id',
        'data.periodic_product_ids.*.id' => 'nullable|numeric|exists:products,id',
        'data.periodic_custom' => 'nullable',
        'data.period_start' => 'nullable|datetime_zoned',
        'data.description' => 'nullable',
    ];
    public $updateValidations = [
        'customer_id' => 'required|numeric|exists:users,id',
        'amount' => 'required|numeric',
        'currency' => 'required|numeric|exists:filters,id',
        'status' => 'required|numeric',
        'flags' => 'nullable|numeric',
        'products.*.id' => 'required|numeric|exists:products,id',
        'extra_product_id' => 'nullable|numeric|exists:products,id',
        'data.periodic_product_ids.*.id' => 'nullable|numeric|exists:products,id',
        'data.periodic_custom' => 'nullable',
        'data.period_start' => 'nullable|datetime_zoned',
        'data.description' => 'nullable',
    ];
    public $searchColumns = [
        'has_exact:customer,name',
        'has_exact:customer.phones,number',
    ];
    public $defaultShowRelations = [
        'products'
    ];

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getValidRelations(): array
    {
        return [
            'customer' => config('larapress.crud.user.provider'),
            'domain' => config('larapress.profiles.routes.domains.provider'),
            'products' => config('larapress.ecommerce.routes.products.provider'),
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
            'created_from' => 'after:created_at',
            'created_to' => 'before:created_at',
            'domain' => 'has:domain:id',
            'status' => 'equals:status',
            'customer_id' => 'equals:customer_id',
            'product_ids' => 'has:products:id',
            'products_count' => 'has-count:products:>=',
            'flags' => 'bitwise:flags',
            'amount' => 'equals:amount',
            'hasDescription' => 'not-null:data->description',
            'due_date_before' => 'before:data->periodic_pay->due_date',
            'due_date_after' => 'after:data->periodic_pay->due_date',
            'purchased_from' => 'after:data->period_start',
            'purchased_to' => 'before:data->period_start',
            'items_count_more' => function (Builder $query, $value) {
                $query->whereHas('cart_items', function ($q) use ($value) {
                    $q->selectRaw('count(*)');
                    $q->havingRaw(DB::raw('count(*) >= ' . $value));
                });
            },
        ];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getValidSortColumns(): array
    {
        return [
            'id' => 'id',
            'customer_id' => 'customer_id',
            'domain_id' => 'domain_id',
            'amount' => 'amount',
            'currency' => 'currency',
            'status' => 'status',
            'flags' => 'flags',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'deleted_at' => 'deleted_at',
            'period_start' => function ($query, string $dir) {
                $query->orderBy('data->period_start', $dir);
            }
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
            CartReport::NAME => CartReport::class,
        ];
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
        if (!$user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            $query->orWhereIn('domain_id', $user->getAffiliateDomainIds());
        }

        return $query;
    }

    /**
     * @param Cart $object
     *
     * @return bool
     */
    public function onBeforeAccess($object): bool
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            return in_array($object->domain_id, $user->getAffiliateDomainIds());
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @param array $args
     * @return void
     */
    public function onBeforeCreate(array $args): array
    {
        return $this->normalizeCartInputData($args);
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
        return $this->normalizeCartInputData($args);
    }

    /**
     * Undocumented function
     *
     * @param Cart $object
     * @param array $input_data
     *
     * @return void
     */
    public function onAfterCreate($object, array $input_data): void
    {
        // normalize attached products
        $this->normalizeCartProductsFromInputData($object, $input_data);

        if ($object->status == Cart::STATUS_ACCESS_COMPLETE) {
            // mark cart purchased
            $timestamp = $object->getPeriodStart();
            /** @var ICartService */
            $cartService = app(ICartService::class);
            $cartService->markCartPurchased(
                $object,
                $timestamp
            );
        } else {
            Helpers::forgetCachedValues([
                'purchasing-cart:' . $object->customer_id,
                'purchased-cart:' . $object->customer_id,
                'user.wallet:' . $object->customer_id,
            ]);

            if ($object->status == Cart::STATUS_ACCESS_GRANTED) {
                CartEvent::dispatch($object, Carbon::now());
            }
        }
    }


    /**
     * Undocumented function
     *
     * @param Cart $object
     * @param array $input_data
     *
     * @return void
     */
    public function onAfterUpdate($object, array $input_data): void
    {
        // remove existing products
        DB::table('carts_products_pivot')->where('cart_id', $object->id)->delete();
        $this->normalizeCartProductsFromInputData($object, $input_data);

        //remove old wallet transaction for this if its a purchase
        $this->removeCartRelatedData($object);

        if ($object->status == Cart::STATUS_ACCESS_COMPLETE) {
            // mark cart purchased
            $timestamp = $object->getPeriodStart();
            /** @var ICartService */
            $cartService = app(ICartService::class);
            $cartService->markCartPurchased(
                $object,
                $timestamp
            );
        } else {
            Helpers::forgetCachedValues([
                'purchasing-cart:' . $object->customer_id,
                'purchased-cart:' . $object->customer_id,
                'user.wallet:' . $object->customer_id,
            ]);

            if ($object->status == Cart::STATUS_ACCESS_GRANTED) {
                CartEvent::dispatch($object, Carbon::now());
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param Cart $object
     *
     * @return void
     */
    public function onAfterDestroy($object): void
    {
        $this->removeCartRelatedData($object);

        Helpers::forgetCachedValues([
            'purchasing-cart:' . $object->customer_id,
            'purchased-cart:' . $object->customer_id,
            'user.wallet:' . $object->customer_id,
        ]);
    }

    /**
     * Undocumented function
     *
     * @param array $args
     *
     * @return array
     */
    protected function normalizeCartInputData(array $args): array
    {

        // normalize flags
        if (isset($args['flags']) && !is_null($args['flags']) && is_numeric($args['flags'])) {
            $args['flags'] = $args['flags'] | Cart::FLAGS_ADMIN;
        } else {
            $args['falgs'] = Cart::FLAGS_ADMIN;
        }

        // normalize
        $periodic_ids = [];
        if (isset($args['data']['periodic_product_ids'])) {
            $periodic_ids = array_values($args['data']['periodic_product_ids']);
            if (isset($periodic_ids[0]['id'])) {
                $periodic_ids = array_map(function ($m) {
                    return $m['id'];
                }, $args['data']['periodic_product_ids']);
            }
        }

        $data = [
            'periodic_product_ids' => $periodic_ids,
            'description' => isset($args['data']['description']) ? $args['data']['description'] : null,
        ];
        if (isset($args['data']['periodic_custom']) && count($args['data']['periodic_custom']) > 0) {
            $data['periodic_custom'] = $args['data']['periodic_custom'];
        }
        if (isset($args['data']['period_start'])) {
            $data['period_start'] = $args['data']['period_start'];
        }
        $args['data'] = $data;

        // find customer domain id
        $class = config('larapress.crud.user.model');
        /** @var IProfileUser */
        $target_user = call_user_func([$class, 'find'], $args['customer_id']);
        $args['domain_id'] = $target_user->getMembershipDomainId();

        return $args;
    }

    /**
     * Undocumented function
     *
     * @param Cart $cart
     * @param array $input_data
     * @return void
     */
    protected function normalizeCartProductsFromInputData(Cart $object, array $input_data)
    {
        // normalize attached products
        $product_ids = isset($input_data['products']) ? array_values($input_data['products']) : [];
        if (isset($product_ids[0]['id'])) {
            $product_ids = array_map(function ($m) {
                return $m['id'];
            }, $product_ids);
        }
        if (isset($input_data['extra_product_id'])) {
            $product_ids = array_merge($product_ids, explode(',', $input_data['extra_product_id']));
        }
        if (count($product_ids) > 0) {
            $itemAmount = $object->amount / count($product_ids);
            foreach ($product_ids as $product_id) {
                $object->products()->attach($product_id, [
                    'data' => [
                        'amount' => $itemAmount,
                        'currency' => $object->currency,
                        'quantity' => 1,
                    ],
                ]);
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param Cart $object
     *
     * @return void
     */
    protected function removeCartRelatedData(Cart $object)
    {
        // remove wallet transaction associated with this cart
        $walletIds = WalletTransaction::query()
            ->where('user_id', $object->customer_id)
            ->whereJsonContains('data->cart_id', $object->id)
            ->where('amount', '<', 0)
            ->get('id')
            ->toArray();
        WalletTransaction::query()->whereIn('id', $walletIds)->delete();
        // remove metrics about wallets
        MetricCounter::query()
            ->whereIn('group', array_map(function ($walletId) {
                return 'tranaction:' . $walletId;
            }, $walletIds))
            ->delete();

        // remove metrics about this cart
        MetricCounter::query()
            ->where('group', 'cart:' . $object->id)
            ->delete();

        // if this is an original cart with periods, remove installments records
        if (BaseFlags::isActive($object->flags, Cart::FLAGS_HAS_PERIODS)) {
            //ids to delete
            $ids = Cart::query()
                ->where('data->periodic_pay->originalCart', $object->id)
                ->select(['id'])
                ->get('id')
                ->toArray();

            Cart::query()
                ->whereIn('id', $ids)
                ->delete();

            // remove wallet transaction associated with these carts
            $innerWalletIds = WalletTransaction::query()
                ->where('user_id', $object->customer_id)
                ->whereIn('data->cart_id', $ids)
                ->where('amount', '<', 0)
                ->select(['id'])
                ->get('id')
                ->toArray();

            WalletTransaction::query()
                ->whereIn('id', $innerWalletIds)
                ->delete();

            // remove metrics about wallets
            MetricCounter::query()
                ->whereIn('group', array_map(function ($walletId) {
                    return 'tranaction:' . $walletId;
                }, $innerWalletIds))
                ->delete();
        }
    }
}
