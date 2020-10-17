<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Larapress\CRUD\BaseFlags;
use Larapress\CRUD\Services\BaseCRUDProvider;
use Larapress\CRUD\Services\ICRUDProvider;
use Larapress\CRUD\Services\IPermissionsMetadata;
use Larapress\CRUD\ICRUDUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\Events\CartPurchasedEvent;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\ECommerce\Services\Banking\Reports\CartPurchasedReport;
use Larapress\Profiles\IProfileUser;
use Larapress\Reports\Services\IMetricsService;
use Larapress\Reports\Services\IReportsService;

class CartCRUDProvider implements ICRUDProvider, IPermissionsMetadata
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
        'description' => 'nullable',
        'products.*.id' => 'required|numeric|exists:products,id',
        'extra_product_id' => 'nullable|numeric|exists:products,id',
        'data.periodic_product_ids.*.id' => 'nullable|numeric|exists:products,id',
        'data.periodic_custom' => 'nullable',
        'data.period_start' => 'nullable|datetime_zoned',
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
    ];
    public $searchColumns = [
        'has_exact:customer,name',
        'has_exact:customer.phones,number',
    ];
    public $validSortColumns = [
        'id',
        'customer_id',
        'domain_id',
        'amount',
        'currency',
        'status',
        'flags',
    ];
    public $validRelations = [
        'customer',
        'domain',
        'products',
        'nested_carts',
        'customer.phones',
    ];
    public $defaultShowRelations = [
        'products'
    ];
    public $filterFields = [
        'from' => 'after:created_at',
        'to' => 'before:created_at',
        'domain' => 'has:domain:id',
        'status' => 'equals:status',
        'customer_id' => 'equals:customer_id',
        'product_ids' => 'has:products:id',
        'flags' => 'bitwise:flags',
    ];


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
        /** @var IProfileUser|ICRUDUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            $query->orWhereIn('domain_id', $user->getAffiliateDomainIds());
            $query->orWhereHas('customer.form_entries', function($q) use($user) {
                $q->where('tags', 'support-group-'.$user->id);
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
        /** @var ICRUDUser|IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
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
            if (isset($args['data']['periodic_product_ids'][0]['id'])) {
                $periodic_ids = array_map(function ($m) { return $m['id']; }, $args['data']['periodic_product_ids']);
            }
        }
        $data = [
            'periodic_product_ids' => $periodic_ids,
            'description' => isset($args['description']) ? $args['description']: null,
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
        $periodic_ids = [];
        if (isset($args['data']['periodic_product_ids'])) {
            $periodic_ids = array_values($args['data']['periodic_product_ids']);
            if (isset($args['data']['periodic_product_ids'][0]['id'])) {
                $periodic_ids = array_map(function ($m) { return $m['id']; }, $args['data']['periodic_product_ids']);
            }
        }
        $data = [
            'periodic_product_ids' => $periodic_ids,
            'description' => isset($args['description']) ? $args['description']: null,
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
            $product_ids = array_map(function($m) { return $m['id']; } ,$input_data['products']);
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
            // update internal fast cache! for balance
            $object->customer->updateUserCache();
            Cache::tags(['purchasing-cart:' . $object->customer->id])->flush();
            Cache::tags(['purchased-cart:' . $object->customer->id])->flush();
            Cache::tags(['user.wallet:' . $object->customer->id])->flush();

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
            $product_ids = array_map(function($m) { return $m['id']; } ,$input_data['products']);
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

        if ($object->status == Cart::STATUS_ACCESS_COMPLETE) {
            $object->flags |= Cart::FLAGS_USER_CART;
            //remove wallet transaction for this purchase
            WalletTransaction::where('user_id', $object->customer_id)->where('data->cart_id', $object->id."")->where('amount', '<', 0)->delete();

            // accept purchase again
            /** @var IBankingService */
            $banking = app(IBankingService::class);
            $banking->markCartPurchased(
                Request::createFromGlobals(),
                $object
            );
        } else {
            if ($object->status == Cart::STATUS_ACCESS_GRANTED) {
                WalletTransaction::where('user_id', $object->customer_id)->where('data->cart_id', $object->id."")->where('amount', '<', 0)->delete();
                CartPurchasedEvent::dispatch($object, time());
            }

            // update internal fast cache! for balance
            $object->customer->updateUserCache();
            Cache::tags(['purchasing-cart:' . $object->customer->id])->flush();
            Cache::tags(['purchased-cart:' . $object->customer->id])->flush();
            Cache::tags(['user.wallet:' . $object->customer->id])->flush();
            $object->customer->updateUserCache('balance');

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
        $wallet = WalletTransaction::query()
            ->where('user_id', $object->customer_id)
            ->whereJsonContains('data->cart_id', $object->id)
            ->where('amount', '<', 0)
            ->first();
        if (!is_null($wallet)) {
            $wallet->delete();
        }

        // update internal fast cache! for balance
        $object->customer->updateUserCache('balance');

        Cache::tags(['purchasing-cart:' . $object->customer->id])->flush();
        Cache::tags(['purchased-cart:' . $object->customer->id])->flush();
        Cache::tags(['user.wallet:' . $object->customer->id])->flush();
    }
}
