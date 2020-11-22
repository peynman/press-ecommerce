<?php

namespace Larapress\ECommerce\Services\CourseSession;

interface ICourseSessionRepository
{

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @return Product[]
     */
    public function getTodayCourseSessions($user);


    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @return Product[]
     */
    public function getWeekCourseSessions($user);
}
