<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\BaseCRUDProvider;
use Larapress\CRUD\Services\ICRUDProvider;
use Larapress\CRUD\Services\IPermissionsMetadata;
use Larapress\CRUD\ICRUDUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\Domain;

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
        'products.*.id' => 'required|numeric|exists:products,id',
        'periodic_product_ids.*.id' => 'nullable|numeric|exists:products,id'
    ];
    public $updateValidations = [
        'customer_id' => 'required|numeric|exists:users,id',
        'amount' => 'required|numeric',
        'currency' => 'required|numeric|exists:filters,id',
        'status' => 'required|numeric',
        'flags' => 'nullable|numeric',
        'products.*.id' => 'required|numeric|exists:products,id',
        'periodic_product_ids.*.id' => 'nullable|numeric|exists:products,id'
    ];
    public $searchColumns = [
        'data'
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
    ];
    public $defaultShowRelations = [
    ];
    public $filterFields = [
        'from' => 'after:created_at',
        'to' => 'before:created_at',
        'domain' => 'has:domain:id',
        'status' => 'equals:status',
        'customer_id' => 'equals:customer_id',
        'flags' => 'bitwise:flags',
    ];

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
        $args['data'] = [
            'periodic_product_ids' => isset($args['periodic_product_ids']) ? array_keys($args['periodic_product_ids']) : [],
        ];

        $class = config('larapress.crud.user.class');
        /** @var IProfileUser */
        $target_user = call_user_func([$class, 'find'], $args['customer_id']);
        $args['domain_id'] = $target_user->getRegistrationDomainId();

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
        $product_ids = array_keys($input_data['products']);
        foreach ($product_ids as $product_id) {
            $object->products()->attach($product_id, [
                'amount' => $input_data['amount'],
                'currency' => $input_data['currency'],
            ]);
        }

        return $object;
    }
}
