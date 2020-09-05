<?php

use Larapress\ECommerce\Services\SupportGroup\ISupportGroupService;


namespace Larapress\ECommerce\Services\SupportGroup;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Profiles\Models\FormEntry;
use Larapress\Profiles\Services\FormEntry\IFormEntryService;
use Larapress\Profiles\IProfileUser;

class SupportGroupService implements ISupportGroupService {

    /**
     * Undocumented function
     *
     * @param SupportGroupUpdateRequest $request
     * @return Response
     */
    public function updateUsersSupportGroup(SupportGroupUpdateRequest $request) {
        $class = config('larapress.crud.user.class');

        $avSupportUserIds = [];
        if ($request->shouldRandomizeSupportIds()) {
            $avSupportUserIds = call_user_func([$class, 'whereHas'], 'roles', function($q) {
                $q->whereIn('id', config('larapress.ecommerce.lms.support_randomizer_role_ids'));
            })->get();
        } else {
            $supportUserId = $request->getSupportUserID();
            $supportUser = call_user_func([$class, 'find'], $supportUserId);
            $supportProfile = !is_null($supportUser->profile) ? $supportUser->profile->data['values'] : [];
            if (! $supportUser->hasRole(config('larapress.ecommerce.lms.support_role_id'))) {
                throw new AppException(AppException::ERR_INVALID_QUERY);
            }
        }

        if ($request->shouldUseAllNoneSupportUsers()) {
            $userIds = User::whereDoesntHave('form_entries', function($q) {
                $q->where('tags', 'LIKE', 'support-group-%');
            })->whereHas('roles', function($q) {
                $q->where('id', config('larapress.ecommerce.lms.customer_role_id'));
            })->get();
        } else {
            $userIds = $request->getUserIds();
        }

        /** @var IFormEntryService */
        $service = app(IFormEntryService::class);

        $totalSupUserIds = count($avSupportUserIds);
        $indexer = 1;
        foreach ($userIds as $userId) {
            if ($request->shouldRandomizeSupportIds()) {
                $supportUser = $avSupportUserIds[$indexer % $totalSupUserIds];
                $supportUserId = $supportUser->id;
                $supportProfile = !is_null($supportUser->profile) ? $supportUser->profile->data['values'] : [];
            }

            if (is_numeric($userId)) {
                $user = call_user_func([$class, 'find'], $userId);
            } else {
                $user = $userId;
                $userId = $user->id;
            }

            $service->updateUserFormEntryTag(
                $request,
                $user,
                config('larapress.ecommerce.lms.support_group_default_form_id'),
                'support-group-'.$supportUserId,
                function ($request, $inputNames, $form, $entry) use($supportUserId, $supportProfile) {
                    return $this->getSupportIdsDataForEntry($entry, $supportUserId, $supportProfile);
                }
            );
            Cache::tags(['user.support:'.$userId])->flush();

            $indexer++;
        }
        return ['message' => 'Success'];
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IProfileUser $user
     * @param IProfileUser|int $supportUser
     * @return Response
     */
    public function updateUserSupportGroup(Request $request, IProfileUser $user, $supportUser) {
        /** @var IFormEntryService */
        $service = app(IFormEntryService::class);

        $class = config('larapress.crud.user.class');
        if (is_numeric($supportUser)) {
            $supportUser = call_user_func([$class, 'find'], $supportUser);
        }
        if (is_null($supportUser)) {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }
        $supportUserId = $supportUser->id;
        $supportProfile = is_null($supportUser->profile) ? [] : $supportUser->profile->data['values'];
        if (! $supportUser->hasRole(config('larapress.ecommerce.lms.support_role_id'))) {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }

        $service->updateUserFormEntryTag(
            $request,
            $user,
            config('larapress.ecommerce.lms.support_group_default_form_id'),
            'support-group-'.$supportUserId,
            function ($request, $inputNames, $form, $entry) use($supportUserId, $supportProfile) {
                return $this->getSupportIdsDataForEntry($entry, $supportUserId, $supportProfile);
            }
        );
        Cache::tags(['user.support:'.$user->id])->flush();
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int|IProfileUser $supportUser
     * @return Response
     */
    public function updateMySupportGroup(Request $request, $supportUser) {
        /** @var IFormEntryService */
        $service = app(IFormEntryService::class);

        $class = config('larapress.crud.user.class');
        if (is_numeric($supportUser)) {
            $supportUser = call_user_func([$class, 'find'], $supportUser);
        }
        if (is_null($supportUser)) {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }
        $supportUserId = $supportUser->id;
        $supportProfile = is_null($supportUser->profile) ? [] : $supportUser->profile->data['values'];
        if (! $supportUser->hasRole(config('larapress.ecommerce.lms.support_role_id'))) {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }

        $service->updateUserFormEntryTag(
            $request,
            Auth::user(),
            config('larapress.ecommerce.lms.support_group_default_form_id'),
            'support-group-'.$supportUserId,
            function ($request, $inputNames, $form, $entry) use($supportUserId, $supportProfile, $supportUser) {
                $data = $this->getSupportIdsDataForEntry($entry, $supportUserId, $supportProfile);
                if (is_null($entry)) {
                    $this->updateUserRegistrationGiftWithIntroducer($request, Auth::user(), $supportUser, false, false);
                }
                return $data;
            }
        );

        return ['message' => 'پشتیبان شما ثبت شد', 'support' => $supportProfile];
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IProfileUser $user
     * @param int|IProfileUser $introducer
     * @param bool $updateSupportGroup
     * @param bool $updateIntroducer
     * @return void
     */
    public function updateUserRegistrationGiftWithIntroducer(Request $request, IProfileUser $user, $introducer, $updateSupportGroup, $updateIntroducer) {
        // add registerar gift based on introducer id
        if (!is_null($introducer)) {
            // add to support group if introducer has support role
            $class = config('larapress.crud.user.class');
            if (is_numeric($introducer)) {
                $introducer = call_user_func([$class ,'find'], $introducer);
            }

            /** @var IFormEntryService */
            $service = app(IFormEntryService::class);


            $service->updateUserFormEntryTag(
                $request,
                $user,
                config('larapress.ecommerce.lms.introducer_default_form_id'),
                'introducer-id-'.$introducer->id,
                function ($req, $form, $entry) use($introducer) {
                    return [
                        'introducer_id' => $introducer->id,
                    ];
                }
            );

            // default gift amount
            $giftAmount = config('larapress.ecommerce.lms.introducers.user_gift.amount');

            if ($introducer->hasRole(config('larapress.ecommerce.lms.support_role_id'))) {
                if ($updateSupportGroup) {
                    $service->updateUserFormEntryTag(
                        $request,
                        $user,
                        config('larapress.ecommerce.lms.support_group_default_form_id'),
                        'support-group-'.$introducer->id,
                        function ($request, $inputNames, $form, $entry) use($introducer) {
                            $values = [
                                'support_user_id' => is_null($entry) || !isset($entry->data['values']['support_user_id']) ? [$introducer->id] :
                                    array_merge($entry->data['values']['support_user_id'], [$introducer->id])
                            ];
                            return $values;
                        }
                    );
                }

                // update default gift amount if support introducer has customized gift
                $supportSettings = FormEntry::query()
                                        ->where('form_id', config('larapress.ecommerce.lms.support_settings_default_form_id'))
                                        ->where('user_id', $introducer->id)
                                        ->first();
                if (!is_null($supportSettings)) {
                    if (isset($supportSettings->data['values']['register_gift'])) {
                        $customGift = floatval($supportSettings->data['values']['register_gift']);
                        if ($customGift > 0) {
                            $giftAmount = $customGift;
                        }
                    }
                }
            }


            /** @var IBankingService */
            $bankService = app(IBankingService::class);

            $userPrevGifts = $bankService->getUserTotalGiftBalance($user, config('larapress.ecommerce.lms.registeration_gift.currency'));
            $giftAmount = $giftAmount - $userPrevGifts;

            if ($giftAmount > 0) {
                $bankService->addBalanceForUser(
                    $request,
                    $user,
                    $giftAmount,
                    config('larapress.ecommerce.lms.introducers.user_gift.currency'),
                    WalletTransaction::TYPE_MANUAL_MODIFY,
                    WalletTransaction::FLAGS_REGISTRATION_GIFT,
                    trans('larapress::ecommerce.banking.messages.wallet-descriptions.introducer_gift_wallet_desc', [
                        'introducer_id' => $introducer->id
                    ])
                );
            }
        } else {
            /** @var IBankingService */
            $bankService = app(IBankingService::class);
            $bankService->addBalanceForUser(
                $request,
                $user,
                config('larapress.ecommerce.lms.registeration_gift.amount'),
                config('larapress.ecommerce.lms.registeration_gift.currency'),
                WalletTransaction::TYPE_MANUAL_MODIFY,
                WalletTransaction::FLAGS_REGISTRATION_GIFT,
                trans('larapress::ecommerce.banking.messages.wallet-descriptions.register_gift_wallet_desc')
            );
        }
    }

    /**
     * @return array
     */
    protected function getSupportIdsDataForEntry($entry, $supportUserId, $supportProfile) {
        $values = [];
        if (is_null($entry) || !isset($entry->data['values']['support_ids']) || Helpers::isAssocArray($entry->data['values']['support_ids'])) {
            $values['support_ids'] = [];
        } else {
            $values['support_ids'] = $entry->data['values']['support_ids'];
        }

        $values['support_ids'][] = [
            'support_user_id' => $supportUserId,
            'support_name' => isset($supportProfile['firstname']) && isset($supportProfile['lastname']) ?
            $supportProfile['firstname'].' '.$supportProfile['lastname'] : 'support-id-'.$supportUserId,
            'updated_at' => Carbon::now(),
        ];
        return $values;
    }
}
