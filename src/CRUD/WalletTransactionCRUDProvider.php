<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Larapress\CRUD\Base\BaseCRUDProvider;
use Larapress\CRUD\Base\ICRUDProvider;
use Larapress\CRUD\Base\IPermissionsMetadata;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\Pages\Models\Page;

class WalletTransactionCRUDProvider implements ICRUDProvider, IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.wallet_transactions.name';
    public $verbs = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
    ];
    public $model = WalletTransaction::class;
    public $createValidations = [
        'user_id' => 'required|numeric|exists:users,id',
        'domain_id' => 'required|numeric|exists:domains,id',
        'amount' => 'required|numeric',
        'currency' => 'required|numeric',
        'type' => 'required|numeric',
	    'flags' => 'nullable|numeric',
    ];
    public $updateValidations = [
        'user_id' => 'required|numeric|exists:users,id',
        'domain_id' => 'required|numeric|exists:domains,id',
        'amount' => 'required|numeric',
        'currency' => 'required|numeric',
        'type' => 'required|numeric',
	    'flags' => 'nullable|numeric',
    ];
    public $autoSyncRelations = [];
    public $validSortColumns = [
        'id',
        'user_id',
        'domain_id',
        'amount',
        'type',
    ];
    public $searchColumns = [
        'id',
        'data',
        'amount',
        'type',
    ];
    public $validRelations = [
        'user',
        'domain'
    ];
    public $validFilters = [];
    public $defaultShowRelations = [];
    public $excludeFromUpdate = [];
    public $filterFields = [];
    public $filterDefaults = [];

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
     * Undocumented function
     *
     * @param [type] $args
     * @return void
     */
    public function onBeforeCreate( $args )
    {
        $args['author_id'] = Auth::user()->id;

        return $args;
    }

    /**
     * @param Builder $query
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function onBeforeQuery($query)
    {
        /** @var ICRUDUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            $query->where('author_id', $user->id);
        }

        return $query;
    }

    /**
     * @param Page $object
     *
     * @return bool
     */
    public function onBeforeAccess($object)
    {
        /** @var ICRUDUser|IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            return $user->id === $object->author_id;
        }

        return true;
    }
}
