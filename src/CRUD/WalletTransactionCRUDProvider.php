<?php

namespace Larapress\ECommerce\CRUD;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Larapress\CRUD\Services\CRUD\BaseCRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\RBAC\IPermissionsMetadata;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\Events\WalletTransactionEvent;
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

    public function getCreateRules(Request $request)
    {
        return [
            'type' => 'required|numeric|in:'.implode(',', [
                WalletTransaction::TYPE_REAL_MONEY,
                WalletTransaction::TYPE_VIRTUAL_MONEY,
            ]),
            'target_user' => 'required|numeric|exists:users,id',
            'amount' => 'required|numeric',
            'currency' => 'required|numeric',
            'flags' => 'nullable|numeric',
            'data.description' => 'required|string',
        ];
    }
    public function getUpdateRules(Request $request)
    {
        return [
            'type' => 'required|numeric|in:'.implode(',', [
                WalletTransaction::TYPE_REAL_MONEY,
                WalletTransaction::TYPE_VIRTUAL_MONEY,
            ]),
            'target_user' => 'required|numeric|exists:users,id',
            'amount' => 'required|numeric',
            'currency' => 'required|numeric',
            'flags' => 'nullable|numeric',
            'data.description' => 'required|string',
        ];
    }
    public $updateValidations = [
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
        'has_exact:user,name',
        'has_exact:user.phones,number',
    ];
    public $defaultShowRelations = [];

    public function getValidRelations()
    {
        return [
            'user' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.users.name').'.view');
            },
            'domain' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.domains.name').'.view');
            },
            'user.phones' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.phone-numbers.name').'.view');
            },
            'user.form_support_user_profile' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.form-entries.name').'.view');
            },
            'user.form_profile_default' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.form-entries.name').'.view');
            },
            'user.form_profile_support' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.form-entries.name').'.view');
            },
            'user.form_support_registration_entry' => function ($user) {
                return $user->hasPermission(config('larapress.profiles.routes.form-entries.name').'.view');
            },
            'user.wallet_balance'  => function ($user) {
                return $user->hasPermission(config('larapress.ecommerce.routes.wallet_transactions.name').'.view');
            },
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
            'type' => 'equals:type',
            'user_id' => 'equals:user_id',
            'withoutGateway' => function ($query, $value) {
                if ($value) {
                    $query->whereNull('data->transaction_id');
                }
            },
            'amount_min' => function ($query, $value) {
                $query->where('amount', '>=', $value);
            },
            'amount_type' => function ($query, $value) {
                if ($value == 1) {
                    $query->where('amount', '<', 0);
                } else {
                    $query->where('amount', '>', 0);
                }
            }
        ];
    }
    /**
     * Undocumented function
     *
     * @param [type] $args
     * @return void
     */
    public function onBeforeCreate($args)
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
            $query->orWhereIn('domain_id', $user->getAffiliateDomainIds());
            $query->orWhereHas('user.form_entries', function ($q) use ($user) {
                $q->where('tags', 'support-group-'.$user->id);
            });
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

    /**
     * Undocumented function
     *
     * @param WalletTransaction $object
     * @param array $input_data
     * @return void
     */
    public function onAfterCreate($object, $input_data)
    {
        WalletTransactionEvent::dispatch($object, time());
    }

    /**
     * Undocumented function
     *
     * @param WalletTransaction $object
     * @param array $input_data
     * @return void
     */
    public function onAfterUpdate($object, $input_data)
    {
        WalletTransactionEvent::dispatch($object, time());
    }

    /**
     * Undocumented function
     *
     * @param WalletTransaction $object
     * @return void
     */
    public function onAfterDestroy($object)
    {
    }
}
