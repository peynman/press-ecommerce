<?php

namespace Larapress\ECommerce\Services\CourseSession;

interface ICourseSessionFormService
{
    /**
     * Undocumented function
     *
     * @param SessionFormRequest $request
     * @param int $sessionId
     * @param FileUpload|null $upload
     * @return void
     */
    public function receiveCourseForm(CourseSessionFormRequest $request, $sessionId, $upload);
}