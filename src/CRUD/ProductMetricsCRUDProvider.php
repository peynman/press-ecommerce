<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\CRUD\BaseCRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\RBAC\IPermissionsMetadata;
use Larapress\ECommerce\IECommerceUser;

/**
 * Filter MetricsCRUDProvider based on product they are reporting on
 *
 */
class ProductMetricsCRUDProvider implements
    ICRUDProvider,
    IPermissionsMetadata
{
    use BaseCRUDProvider;

    public function getValidRelations()
    {
        return [
            'domain',
            'group_cart',
            'group_cart.customer',
            'group_cart.customer.form_profile_default',
            'group_cart.customer.form_support_user_profile',
            'group_cart.customer.form_support_registration_entry',
        ];
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
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            if ($user->hasRole(config('larapress.lcms.owner_role_id'))) {
                $ownerEntries = collect($user->getOwenedProductsIds())->map(function ($id) {
                    return 'product[.]'.$id.'[.].*';
                })->toArray();
                $query->whereRaw('metrics_counters.key REGEXP \''.implode('|', $ownerEntries).'\'');
            } elseif ($user->hasRole(config('larapress.lcms.support_role_id'))) {
                $query->where('key', 'LIKE', '%.'.$user->id);
            } else {
                $query->whereHas('domains', function ($q) use ($user) {
                    $q->whereIn('id', $user->getAffiliateDomainIds());
                });
            }
        }

        return $query;
    }
}
