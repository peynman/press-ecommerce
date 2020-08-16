<?php

namespace Larapress\ECommerce\Services\SupportGroup;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
    public function updateUserSupportGroup(Request $request, $user, $supportUser);
}
