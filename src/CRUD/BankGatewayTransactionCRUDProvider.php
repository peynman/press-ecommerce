<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\CRUD\Traits\CRUDProviderTrait;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDVerb;
use Larapress\ECommerce\Services\Banking\Reports\GatewayTransactionReport;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\Domain;

class BankGatewayTransactionCRUDProvider implements ICRUDProvider
{
    use CRUDProviderTrait;

    public $name_in_config = 'larapress.ecommerce.routes.bank_gateway_transactions.name';
    public $model_in_config = 'larapress.ecommerce.routes.bank_gateway_transactions.model';
    public $compositions_in_config = 'larapress.ecommerce.routes.bank_gateway_transactions.compositions';

    public $verbs = [
        ICRUDVerb::VIEW,
        ICRUDVerb::SHOW,
        ICRUDVerb::DELETE,
    ];
    public $searchColumns = [
        'has:customer.phones.number',
        'tracking_code',
        'reference_code',
    ];
    public $validSortColumns = [
        'id',
        'flags',
        'status',
        'domain_id',
        'user_id',
        'cart_id',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    public $defaultShowRelations = [
        'customer',
        'domain',
        'cart',
    ];
    public $filterFields = [
        'created_from' => 'after:created_at',
        'created_to' => 'before:created_at',
        'customer_id' => 'equals:customer_id',
        'domain' => 'in:domain_id',
        'status' => 'equals:status',
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
            'cart' => config('larapress.ecommerce.routes.carts.provider'),
            'bank_gateway' => config('larapress.ecommerce.routes.bank_gateways.provider'),
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
            GatewayTransactionReport::NAME => GatewayTransactionReport::class,
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
        if (! $user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            $query->whereIn('domain_id', $user->getAffiliateDomainIds());
        }

        return $query;
    }

    /**
     * @param Domain $object
     *
     * @return bool
     */
    public function onBeforeAccess($object): bool
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            return in_array($object->id, $user->getAffiliateDomainIds());
        }

        return true;
    }
}
