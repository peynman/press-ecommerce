<?php

namespace Larapress\ECommerce\Services\CourseSession;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

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


    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $sessionId
     * @param int $entryId
     * @param int $fileId
     * @return Response
     */
    public function serveSessionFormFile($request, $sessionId, $entryId, $fileId);

    /**
     * Undocumented function
     *
     * @param CourseSessionPresenceRequest $request
     * @param int $sessionId
     * @return void
     */
    public function markCourseSessionPresence(CourseSessionPresenceRequest $request, $sessionId);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IProfileUser $user
     * @param string $sessionId
     * @param integer $duration
     * @param Carbon $at
     * @return void
     */
    public function addCourseSessionPresenceMarkForSession($request, $user, $sessionId, $duration, $at);

    /**
     * Undocumented function
     *
     * @param int $sessionId
     * @return array
     */
    public function getCourseSessionPresenceReport(Request $request, $sessionId);
}
