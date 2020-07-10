<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Base\BaseCRUDProvider;
use Larapress\CRUD\Base\ICRUDProvider;
use Larapress\CRUD\Base\IPermissionsMetadata;
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
        'domain_id' => 'required|numeric|exists:domains,id',
        'amount' => 'required|numeric',
        'currency' => 'required|numeric|exists:filters,id',
        'status' => 'required|numeric',
        'data' => 'nullable',
        'flags' => 'nullable|numeric',
        'items.*.id' => 'nullable|numeric|exists:products,id',
    ];
    public $updateValidations = [
        'customer_id' => 'required|numeric|exists:users,id',
        'domain_id' => 'required|numeric|exists:domains,id',
        'amount' => 'required|numeric',
        'currency' => 'required|numeric|exists:filters,id',
        'status' => 'required|numeric',
        'data' => 'nullable',
        'flags' => 'nullable|numeric',
        'items.*.id' => 'nullable|numeric|exists:products,id',
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
     * @param Builder $query
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function onBeforeQuery($query)
    {
        /** @var IProfileUser|ICRUDUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            $query->whereHas('domains', function($q) use($user) {
                $q->whereIn('id', $user->getAffiliateDomainIds());
            });
        }

        return $query;
    }

    /**
     * @param Domain $object
     *
     * @return bool
     */
    public function onBeforeAccess($object)
    {
        /** @var ICRUDUser|IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            return in_array($object->id, $user->getAffiliateDomainIds());
        }

        return true;
    }
}
