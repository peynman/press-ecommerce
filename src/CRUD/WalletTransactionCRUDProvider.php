<?php

namespace Larapress\ECommerce\CRUD;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\CRUD\Traits\CRUDProviderTrait;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Services\CRUD\ICRUDService;
use Larapress\CRUD\Services\CRUD\ICRUDVerb;
use Larapress\ECommerce\Controllers\WalletTransactionController;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Wallet\Reports\WalletTransactionReport;
use Larapress\ECommerce\Services\Wallet\WalletTransactionEvent;
use Larapress\Profiles\IProfileUser;
use Larapress\Reports\Models\MetricCounter;

class WalletTransactionCRUDProvider implements ICRUDProvider
{
    use CRUDProviderTrait;

    public $name_in_config = 'larapress.ecommerce.routes.wallet_transactions.name';
    public $model_in_config = 'larapress.ecommerce.routes.wallet_transactions.model';
    public $compositions_in_config = 'larapress.ecommerce.routes.wallet_transactions.compositions';

    public $validSortColumns = [
        'id',
        'user_id',
        'domain_id',
        'amount',
        'type',
        'created_at',
        'updated_at',
        'deleted_at',
    ];
    public $searchColumns = [
        'has_exact:user,name',
        'has_exact:user.phones,number',
    ];

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getPermissionVerbs(): array
    {
        return [
            ICRUDVerb::VIEW,
            ICRUDVerb::SHOW,
            ICRUDVerb::CREATE,
            ICRUDVerb::EDIT,
            ICRUDVerb::DELETE,
            'any.request_unverified' => [
                'uses' => '\\'.WalletTransactionController::class.'@requestUnverifiedWalletTransaction',
                'methods' => ['POST'],
                'url' => config('larapress.ecommerce.routes.wallet_transactions.name').'/request',
            ],
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
            WalletTransactionReport::NAME => WalletTransactionReport::class,
        ];
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     *
     * @return array
     */
    public function getCreateRules(Request $request): array
    {
        return [
            'type' => 'required|numeric|in:' . implode(',', [
                WalletTransaction::TYPE_REAL_MONEY,
                WalletTransaction::TYPE_VIRTUAL_MONEY,
                WalletTransaction::TYPE_UNVERIFIED,
            ]),
            'target_user' => 'required|numeric|exists:users,id',
            'amount' => 'required|numeric',
            'currency' => 'required|numeric',
            'flags' => 'nullable|numeric',
            'data.description' => 'required|string',
        ];
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     *
     * @return array
     */
    public function getUpdateRules(Request $request): array
    {
        return [
            'type' => 'required|numeric|in:' . implode(',', [
                WalletTransaction::TYPE_REAL_MONEY,
                WalletTransaction::TYPE_VIRTUAL_MONEY,
                WalletTransaction::TYPE_UNVERIFIED,
            ]),
            'target_user' => 'required|numeric|exists:users,id',
            'amount' => 'required|numeric',
            'currency' => 'required|numeric',
            'flags' => 'nullable|numeric',
            'data.description' => 'required|string',
        ];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getValidRelations(): array
    {
        return [
            'user' => config('larapress.crud.user.provider'),
            'domain' => config('larapress.profiles.routes.domains.provider'),
        ];
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getFilterFields(): array
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
     * @param array $args
     *
     * @return array
     */
    public function onBeforeCreate(array $args): array
    {
        /** @var ICRUDService */
        $crudService = app(ICRUDService::class);
        /** @var IProfileUser */
        $targetUser = User::find($args['target_user']);
        /** @var ICRUDProvider */
        $userProvider = $crudService->makeCompositeProvider(config('larapress.crud.user.provider'));
        if ($userProvider->onBeforeAccess($targetUser)) {
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
     * @param array $args
     *
     * @return array
     */
    public function onBeforeUpdate($args): array
    {
        /** @var ICRUDService */
        $crudService = app(ICRUDService::class);
        /** @var IProfileUser */
        $targetUser = User::find($args['target_user']);
        $provider = $crudService->makeCompositeProvider(config('larapress.crud.user.provider'));

        if ($provider->onBeforeAccess($targetUser)) {
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
     * @return Builder
     */
    public function onBeforeQuery(Builder $query): Builder
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            $query->orWhereIn('domain_id', $user->getAffiliateDomainIds());
        }

        return $query;
    }

    /**
     * @param  $object
     *
     * @return bool
     */
    public function onBeforeAccess($object): bool
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            return in_array($object->domain_id, $user->getAffiliateDomainIds());
        }

        return true;
    }

    /**
     * Undocumented function
     *
     * @param WalletTransaction $object
     * @param array $input_data
     *
     * @return void
     */
    public function onAfterCreate($object, array $input_data): void
    {
        WalletTransactionEvent::dispatch($object, Carbon::now());
    }

    /**
     * Undocumented function
     *
     * @param WalletTransaction $object
     * @param array $input_data
     *
     * @return void
     */
    public function onAfterUpdate($object, array $input_data): void
    {
        // remove related data
        $this->removeWalletTransactionRelatedDate($object);
        // fire wallet event
        WalletTransactionEvent::dispatch($object, Carbon::now());
    }

    /**
     * Undocumented function
     *
     * @param WalletTransaction $object
     *
     * @return void
     */
    public function onAfterDestroy($object): void
    {
        // remove related data
        $this->removeWalletTransactionRelatedDate($object);
    }

    /**
     * Undocumented function
     *
     * @param WalletTransaction $object
     * @return void
     */
    protected function removeWalletTransactionRelatedDate(WalletTransaction $object)
    {
        // remove metrics about this cart
        MetricCounter::query()
            ->where('group', 'transaction:' . $object->id)
            ->delete();
    }
}
