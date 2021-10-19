<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\CRUD\Traits\CRUDProviderTrait;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDVerb;
use Larapress\ECommerce\Controllers\GiftCodeController;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\ECommerce\Services\Cart\Reports\GiftCodeReport;
use Larapress\Profiles\IProfileUser;

class GiftCodeCRUDProvider implements ICRUDProvider
{
    use CRUDProviderTrait;

    public $name_in_config = 'larapress.ecommerce.routes.gift_codes.name';
    public $model_in_config = 'larapress.ecommerce.routes.gift_codes.model';
    public $compositions_in_config = 'larapress.ecommerce.routes.gift_codes.compositions';

    public $searchColumns = [
        'code'
    ];
    public $validSortColumns = [
        'id',
        'author_id',
        'amount',
        'currency',
        'status',
        'flags',
        'created_at',
        'updated_at',
        'deleted_at',
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
            ICRUDVerb::CREATE.'.duplicate' => [
                'methods' => ['POST'],
                'uses' => '\\'.GiftCodeController::class.'@duplicateGiftCode',
                'url' => config('larapress.ecommerce.routes.gift_codes.name').'/duplicate',
            ]
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
            GiftCodeReport::NAME => GiftCodeReport::class,
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
            'author' => config('larapress.crud.user.provider'),
            'use_list' => config('larapress.ecommerce.routes.gift_code_usage.provider'),
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
            'amount' => 'required|numeric',
            'currency' => 'required|numeric|in:'.implode(',', [config('larapress.ecommerce.banking.currency.id')]),
            'code' => 'required|string|min:6|regex:/(^[A-Za-z0-9-_.]+$)+/|unique:gift_codes,code',
            'data.value' => 'required_without:data.gift_same_amount|numeric',
            'data.gift_same_amount' => 'nullable|boolean',
            'data.min_amount' => 'nullable|numeric',
            'data.min_items' => 'nullable|numeric',
            'data.products.*' => 'nullable|exists:products,id',
            'data.specific_ids.*' => 'nullable|exists:users,id',
            'data.multi_time_use' => 'nullable|boolean',
            'data.fixed_only' => 'nullable|boolean',
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
            'amount' => 'required|numeric',
            'currency' => 'required|numeric|in:'.implode(',', [config('larapress.ecommerce.banking.currency.id')]),
            'code' => 'required|string|min:6|regex:/(^[A-Za-z0-9-_.]+$)+/|unique:gift_codes,code,'.$request->route('id'),
            'data.value' => 'required_without:data.gift_same_amount|numeric',
            'data.gift_same_amount' => 'nullable|boolean',
            'data.min_amount' => 'nullable|numeric',
            'data.min_items' => 'nullable|numeric',
            'data.products.*' => 'nullable|exists:products,id',
            'data.specific_ids.*' => 'nullable|exists:users,id',
            'data.multi_time_use' => 'nullable|boolean',
            'data.fixed_only' => 'nullable|boolean',
        ];
    }

    /**
     * @param Builder $query
     *
     * @return
     */
    public function onBeforeQuery(Builder $query): Builder
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            $query->where('author_id', $user->id);
        }

        return $query;
    }

    /**
     * @param GiftCode $object
     *
     * @return bool
     */
    public function onBeforeAccess($object): bool
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            return $object->author_id == $user->id;
        }

        return true;
    }

    /**
     * @param array $args
     *
     * @return array
     */
    public function onBeforeCreate(array $args): array
    {
        /** @var IProfileUser $user */
        $user = Auth::user();
        $args['author_id'] = $user->id;
        return $args;
    }
}
