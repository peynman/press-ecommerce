<?php

namespace Larapress\ECommerce\Services\CourseSession;

interface ICourseSessionRepository
{
    /**
     * Undocumented function
     *
     * @param SessionFormRequest $request
     * @param int $sessionId
     * @param FileUpload|null $upload
     * @return void
     */
    public function getTodayCourseSessions($user);
}
