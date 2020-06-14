<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Base\BaseCRUDProvider;
use Larapress\CRUD\Base\ICRUDProvider;
use Larapress\CRUD\Base\IPermissionsMetadata;
use Larapress\CRUD\ICRUDUser;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\Domain;

class BankGatewayTransactionCRUDProvider implements ICRUDProvider, IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.bank_gateway_transactions.name';
    public $verbs = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
    ];
    public $model = BankGatewayTransaction::class;
    public $createValidations = [
        'bank_gateway_id' => 'required|numeric|exists:bank_gateways,id',
        'domain_id' => 'required|numeric|exists:domains,id',
        'customer_id' => 'required|numeric|exists:users,id',
		'amount' => 'required|numeric',
		'currency' => 'required|numeric|exists:filters,id',
		'tracking_code' => 'nullable|string',
		'reference_code' => 'nullable|string',
		'status' => 'required|numeric',
		'flags' => 'nullable|numeric',
		'data' => 'nullable|json',
    ];
    public $updateValidations = [
        'bank_gateway_id' => 'required|numeric|exists:bank_gateways,id',
        'domain_id' => 'required|numeric|exists:domains,id',
        'customer_id' => 'required|numeric|exists:users,id',
		'amount' => 'required|numeric',
		'currency' => 'required|numeric|exists:filters,id',
		'tracking_code' => 'nullable|string',
		'reference_code' => 'nullable|string',
		'status' => 'required|numeric',
		'flags' => 'nullable|numeric',
		'data' => 'nullable|json',
    ];
    public $searchColumns = [
        'name',
        'data',
    ];
    public $validSortColumns = [
        'id',
        'name',
        'created_at',
        'updated_at',
        'flags',
    ];
    public $validRelations = [
        'author',
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
