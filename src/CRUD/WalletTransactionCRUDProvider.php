<?php

namespace Larapress\ECommerce\CRUD;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\BaseCRUDProvider;
use Larapress\CRUD\Services\ICRUDProvider;
use Larapress\CRUD\Services\IPermissionsMetadata;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\Pages\Models\Page;
use Larapress\Profiles\CRUD\UserCRUDProvider;

class WalletTransactionCRUDProvider implements ICRUDProvider, IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.wallet_transactions.name';
    public $verbs = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
        self::REPORTS,
    ];
    public $model = WalletTransaction::class;
    public $createValidations = [
        'target_user' => 'required|numeric|exists:users,id',
        'amount' => 'required|numeric',
        'currency' => 'required|numeric',
        'flags' => 'nullable|numeric',
        'data.description' => 'required|string',
    ];
    public $updateValidations = [
        'target_user' => 'required|numeric|exists:users,id',
        'amount' => 'required|numeric',
        'currency' => 'required|numeric',
	    'flags' => 'nullable|numeric',
        'data.description' => 'required|string',
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
    public $defaultShowRelations = [];
    public $excludeIfNull = [];
    public $filterFields = [
        'from' => 'after:created_at',
        'to' => 'before:created_at',
        'domain' => 'has:domain:id',
        'type' => 'equals:type',
        'user_id' => 'equals:user_id',
    ];
    public $filterDefaults = [];

    /**
     * Undocumented function
     *
     * @param [type] $args
     * @return void
     */
    public function onBeforeCreate( $args )
    {
        /** @var IProfileUser|ICRUDUser */
        $targetUser = User::find($args['target_user']);
        $userCrudClass = config('larapress.crud.user.crud-provider');
        /** @var UserCRUDProvider */
        $userCrud = new $userCrudClass();
        if ($userCrud->onBeforeAccess($targetUser)) {
            $args['user_id'] = $targetUser->id;
            $args['domain_id'] = $targetUser->getMembershipDomainId();
        } else {
            throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
        }

        $args['type'] = WalletTransaction::TYPE_MANUAL_MODIFY;

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
        /** @var IProfileUser|ICRUDUser */
        $targetUser = User::find($args['target_user']);
        $userCrudClass = config('larapress.crud.user.crud-provider');
        /** @var UserCRUDProvider */
        $userCrud = new $userCrudClass();
        if ($userCrud->onBeforeAccess($targetUser)) {
            $args['user_id'] = $targetUser->id;
            $args['domain_id'] = $targetUser->getMembershipDomainId();
        } else {
            throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
        }

        if (isset($args['type'])) {
            unset($args['type']);
        }

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
            $query->whereHas('domain', $user->getAffiliateDomainIds());
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
            return in_array($object->domain_id, $user->getAffiliateDomainIds());
        }

        return true;
    }
}
