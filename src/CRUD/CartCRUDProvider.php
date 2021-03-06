<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Larapress\CRUD\BaseFlags;
use Larapress\CRUD\Services\CRUD\BaseCRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\RBAC\IPermissionsMetadata;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\ECommerce\Services\Cart\CartPurchasedEvent;
use Larapress\ECommerce\Services\Cart\CartPurchasedReport;
use Larapress\Profiles\IProfileUser;
use Larapress\Reports\Models\MetricCounter;
use Larapress\Reports\Services\IMetricsService;
use Larapress\Reports\Services\IReportsService;

class CartCRUDProvider implements
    ICRUDProvider,
    IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.carts.name';
    public $verbs = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
        self::REPORTS,
    ];
    public $model = Cart::class;
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
    public function getSummerizableColumns()
    {
        return [
            'amount' => function ($query, $params) {
                return $query->sum('amount');
            }
        ];
    }

    public function getValidRelations()
    {
        return [
            'customer' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.users.name').'.view');
            },
            'domain' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.domains.name').'.view');
            },
            'customer.phones' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.phone-numbers.name').'.view');
            },
            'customer.form_support_user_profile' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.form-entries.name').'.view');
            },
            'customer.form_profile_default' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.form-entries.name').'.view');
            },
            'customer.form_profile_support' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.form-entries.name').'.view');
            },
            'customer.form_support_registration_entry' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.form-entries.name').'.view');
            },
            'customer.wallet_balance'  => function ($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.wallet_transactions.name').'.view');
            },
            'products' => function ($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.products.name').'.view');
            },
            'nested_carts',
        ];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getFilterFields()
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
                    $q->havingRaw(DB::raw('count(*) >= '.$value));
                });
            },
        ];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getValidSortColumns()
    {
        return [
            'id' => 'id',
            'customer_id' => 'customer_id',
            'domain_id' => 'domain_id',
            'amount' => 'amount',
            'currency' => 'currency',
            'status' => 'status',
            'flags' => 'flags',
            'period_start' => function ($query, string $dir) {
                $query->orderBy('data->period_start', $dir);
            }
        ];
    }

    /**
     *
     */
    public function getReportSources()
    {
        /** @var IReportsService */
        $service = app(IReportsService::class);
        /** @var IMetricsService */
        $metrics = app(IMetricsService::class);
        return [
            new CartPurchasedReport($service, $metrics),
        ];
    }

    /**
     * @param Builder $query
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function onBeforeQuery($query)
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            $query->orWhereIn('domain_id', $user->getAffiliateDomainIds());
            $query->orWhereHas('customer.form_entries', function ($q) use ($user) {
                $q->where('tags', 'support-group-' . $user->id);
            });
        }

        return $query;
    }

    /**
     * @param Cart $object
     *
     * @return bool
     */
    public function onBeforeAccess($object)
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            return in_array($object->domain_id, $user->getAffiliateDomainIds());
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @param [type] $args
     * @return void
     */
    public function onBeforeCreate($args)
    {
        $args['flags'] = Cart::FLAGS_ADMIN;
        $periodic_ids = [];
        if (isset($args['data']['periodic_product_ids'])) {
            $periodic_ids = array_values($args['data']['periodic_product_ids']);
            if (isset($periodic_ids[0]['id'])) {
                $periodic_ids = array_map(function ($m) {
                    return $m['id'];
                }, $periodic_ids);
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

        $class = config('larapress.crud.user.class');
        /** @var IProfileUser */
        $target_user = call_user_func([$class, 'find'], $args['customer_id']);
        $args['domain_id'] = $target_user->getMembershipDomainId();

        return $args;
    }


    /**
     * Undocumented function
     *
     * @param [type] $args
     * @return void
     */
    public function onBeforeUpdate($args)
    {
        if (isset($args['flags']) && !is_null($args['flags']) && is_numeric($args['flags'])) {
            $args['flags'] = $args['flags'] | Cart::FLAGS_ADMIN;
        } else {
            $args['falgs'] = Cart::FLAGS_ADMIN;
        }

        $periodic_ids = [];
        if (isset($args['data']['periodic_product_ids'])) {
            $periodic_ids = array_values($args['data']['periodic_product_ids']);
            if (isset($args['data']['periodic_product_ids'][0]['id'])) {
                $periodic_ids = array_map(function ($m) {
                    return $m['id'];
                }, $args['data']['periodic_product_ids']);
            }
        }
        $data = [
            'periodic_product_ids' => $periodic_ids,
            'description' => isset($args['data']['description']) ? $args['data']['description'] : null,
            'periodic_payments' => isset($args['data']['periodic_payments']) ? $args['data']['periodic_payments'] : [],
            'gift_code' => isset($args['data']['gift_code']) ? $args['data']['gift_code'] : [],
        ];

        if (isset($args['data']['periodic_custom']) && count($args['data']['periodic_custom']) > 0) {
            $data['periodic_custom'] = $args['data']['periodic_custom'];
        }
        if (isset($args['data']['period_start'])) {
            $data['period_start'] = $args['data']['period_start'];
        }
        $args['data'] = $data;

        $class = config('larapress.crud.user.class');
        /** @var IProfileUser */
        $target_user = call_user_func([$class, 'find'], $args['customer_id']);
        $args['domain_id'] = $target_user->getMembershipDomainId();

        return $args;
    }

    /**
     * Undocumented function
     *
     * @param Cart $object
     * @param [type] $input_data
     * @return void
     */
    public function onAfterCreate($object, $input_data)
    {
        $product_ids = isset($input_data['products']) ? array_keys($input_data['products']) : [];
        if (isset($input_data['products'][0]['id'])) {
            $product_ids = array_map(function ($m) {
                return $m['id'];
            }, $input_data['products']);
        }
        if (isset($input_data['extra_product_id'])) {
            $product_ids[] = $input_data['extra_product_id'];
        }

        foreach ($product_ids as $product_id) {
            $object->products()->attach($product_id, [
                'amount' => $input_data['amount'],
                'currency' => $input_data['currency'],
            ]);
        }

        if ($object->status == Cart::STATUS_ACCESS_COMPLETE) {
            $object->flags |= Cart::FLAGS_USER_CART;
            /** @var IBankingService */
            $banking = app(IBankingService::class);
            $banking->markCartPurchased(
                Request::createFromGlobals(),
                $object
            );
        } else {
            Cache::tags(['purchasing-cart:' . $object->customer_id])->flush();
            Cache::tags(['purchased-cart:' . $object->customer_id])->flush();
            Cache::tags(['user.wallet:' . $object->customer_id])->flush();

            if ($object->status == Cart::STATUS_ACCESS_GRANTED) {
                CartPurchasedEvent::dispatch($object, time());
            }
        }

        return $object;
    }


    /**
     * Undocumented function
     *
     * @param Cart $object
     * @param [type] $input_data
     * @return void
     */
    public function onAfterUpdate($object, $input_data)
    {
        $product_ids = isset($input_data['products']) ? array_keys($input_data['products']) : [];
        if (isset($input_data['products'][0]['id'])) {
            $product_ids = array_map(function ($m) {
                return $m['id'];
            }, $input_data['products']);
        }
        if (isset($input_data['extra_product_id'])) {
            $product_ids[] = $input_data['extra_product_id'];
        }

        // remove existing products
        DB::table('carts_products_pivot')->where('cart_id', $object->id)->delete();
        // attach new product again
        foreach ($product_ids as $product_id) {
            $object->products()->attach($product_id, [
                'amount' => $input_data['amount'],
                'currency' => $input_data['currency'],
            ]);
        }

        //remove old wallet transaction for this if its a purchase
        if (in_array($object->status, [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED])) {
            WalletTransaction::where('user_id', $object->customer_id)
                ->where('data->cart_id', $object->id . "")
                ->where('amount', '<', 0)
                ->delete();
        }

        // remove metrics about this cart
        MetricCounter::query()
            ->where('group', 'cart:' . $object->id)
            ->delete();

        if ($object->status == Cart::STATUS_ACCESS_COMPLETE) {
            $object->flags |= Cart::FLAGS_USER_CART;

            // accept purchase again
            // add metrics again
            /** @var IBankingService */
            $banking = app(IBankingService::class);
            $banking->markCartPurchased(
                Request::createFromGlobals(),
                $object
            );
        } else {
            // accept purchase internally again
            if ($object->status == Cart::STATUS_ACCESS_GRANTED) {
                CartPurchasedEvent::dispatch($object, time());
            }

            Cache::tags(['purchasing-cart:' . $object->customer_id])->flush();
            Cache::tags(['purchased-cart:' . $object->customer_id])->flush();
            Cache::tags(['user.wallet:' . $object->customer_id])->flush();
        }

        return $object;
    }

    /**
     * Undocumented function
     *
     * @param Cart $object
     * @return void
     */
    public function onAfterDestroy($object)
    {
        // remove wallet transaction associated with this cart
        WalletTransaction::query()
            ->where('user_id', $object->customer_id)
            ->whereJsonContains('data->cart_id', $object->id)
            ->where('amount', '<', 0)
            ->delete();

        // remove metrics about this cart
        MetricCounter::query()
            ->where('group', 'cart:' . $object->id)
            ->delete();

        // if this is a original cart, remove installments records
        if (BaseFlags::isActive($object->flags, Cart::FLAGS_HAS_PERIODS)) {
            //ids to delete
            $ids = Cart::query()
                ->where('data->periodic_pay->originalCart', $object->id)
                ->select(['id'])
                ->get('id');
            Cart::query()
                ->whereIn('id', $ids)
                ->delete();
            // remove wallet transaction associated with this cart
            $innerIds = WalletTransaction::query()
                ->where('user_id', $object->customer_id)
                ->whereIn('data->cart_id', $ids->toArray())
                ->where('amount', '<', 0)
                ->select(['id'])
                ->get('id');
            WalletTransaction::query()->whereIn('id', $innerIds)->delete();
        }

        Cache::tags(['purchasing-cart:' . $object->customer_id])->flush();
        Cache::tags(['purchased-cart:' . $object->customer_id])->flush();
        Cache::tags(['user.wallet:' . $object->customer_id])->flush();
    }
}
