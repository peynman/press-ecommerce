<?php

namespace Larapress\ECommerce\Services\SupportGroup;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapress\Profiles\IProfileUser;

interface ISupportGroupService {
    /**
     * Undocumented function
     *
     * @param SupportGroupUpdateRequest $request
     * @return Response
     */
    public function updateUsersSupportGroup(SupportGroupUpdateRequest $request);


    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IProfileUser $user
     * @param IProfileUser|int $supportUser
     * @return Response
     */
    public function updateUserSupportGroup(Request $request, IProfileUser $user, $supportUser);


    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int|IProfileUser $supportUser
     * @return Response
     */
    public function updateMySupportGroup(Request $request, $supportUser);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IProfileUser $user
     * @param int $introducerId
     * @param bool $updateSupportGroup
     * @param bool $updateIntroducer
     * @return void
     */
    public function updateUserRegistrationGiftWithIntroducer(Request $request, IProfileUser $user, $introducerId, $updateSupportGroup, $updateIntroducer);
}
