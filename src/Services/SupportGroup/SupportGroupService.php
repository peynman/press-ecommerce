<?php

use Larapress\ECommerce\Services\SupportGroup\ISupportGroupService;


namespace Larapress\ECommerce\Services\SupportGroup;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Larapress\CRUD\Extend\Helpers;
use Larapress\Profiles\Models\FormEntry;
use Larapress\Profiles\Services\IFormEntryService;

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
                $q->where('id', config('larapress.ecommerce.lms.support_role_id'));
            })->get();
        } else {
            $supportUserId = $request->getSupportUserID();
            $supportUser = call_user_func([$class, 'find'], $supportUserId);
            $supportProfile = !is_null($supportUser->profile) ? $supportUser->profile->data['values'] : [];
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
    public function updateUserSupportGroup(Request $request, $user, $supportUser) {
        /** @var IFormEntryService */
        $service = app(IFormEntryService::class);

        $class = config('larapress.crud.user.class');
        if (is_numeric($supportUser)) {
            $supportUser = call_user_func([$class, 'find'], $supportUser);
        }
        $supportUserId = $supportUser->id;
        $supportProfile = is_null($supportUser->profile) ? [] : $supportUser->profile->data['values'];

        $service->updateUserFormEntryTag(
            $request,
            $user,
            config('larapress.ecommerce.lms.support_group_default_form_id'),
            'support-group-'.$supportUserId,
            function ($request, $inputNames, $form, $entry) use($supportUserId, $supportProfile) {
                return $this->getSupportIdsDataForEntry($entry, $supportUserId, $supportProfile);
            }
        );
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
