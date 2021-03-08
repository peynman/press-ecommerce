<?php

namespace Larapress\ECommerce\Services\CourseSession;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\Ecommerce\Services\FileUpload\IFileUploadService;
use Illuminate\Http\Response;

/**
 * @group CourseSession management
 *
 * Update and manage form entries for Course management
 */
class CourseSessionFormController extends Controller
{
    public static function registerPublicApiRoutes()
    {
        Route::post('course-session/{session_id}/upload-form', '\\' . self::class . '@receiveCourseForm')
            ->name('course-sessions.any.upload-form');
        Route::post('course-session/{session_id}/presence-form', '\\' . self::class . '@markCoursePresence')
            ->name('course-sessions.any.presence-form');
        Route::post('course-session/{session_id}/presence-report', '\\' . self::class . '@getCoursePresenceReport')
            ->name(config('larapress.ecommerce.routes.products.name').'.reports.presence');
    }

    public static function registerWebRoutes()
    {
        Route::any('course-session/{session_id}/entry/{entry_id}/download/{file_id}', '\\' . self::class . '@serveCourseFormFile')
            ->name('file-uploads.view.session.file');
    }

    /**
     * Receive a file for a session
     *
     * @urlParam session_id integer required The ID of the session to add this file to.
     *
     * @param ICourseSessionFormService $courseService
     * @param IFileUploadService $service
     * @param CourseSessionFormRequest $request
     * @param int $session_id
     * @return Response
     */
    public function receiveCourseForm(ICourseSessionFormService $courseService, IFileUploadService $service, CourseSessionFormRequest $request, $session_id)
    {
        return $service->receiveUploaded($request, function (UploadedFile $file) use ($request, $courseService, $service, $session_id) {
            $upload = $service->processUploadedFile($request, $file);
            return $courseService->receiveCourseForm($request, $session_id, $upload);
        });
    }


    /**
     * Receive a file for a session
     *
     * @urlParam session_id integer required The ID of the session to open a file from
     * @urlParam entry_id integer required The ID of the form entry which contains the from for session.
     * @urlParam file_id integer required The ID of the file to display.
     *
     * @param ICourseSessionFormService $courseService
     * @param Request $request
     * @param int $session_id
     * @param int $entry_id
     * @param int $file_id
     * @return Response
     */
    public function serveCourseFormFile(ICourseSessionFormService $courseService, Request $request, $session_id, $entry_id, $file_id)
    {
        return $courseService->serveSessionFormFile($request, $session_id, $entry_id, $file_id);
    }


    /**
     * Add a presence form entry in a session for logged in user.
     *
     * @urlParam session_id int required The ID of the session to add a new presence record.
     *
     * @param ICourseSessionFormService $courseService
     * @param CourseSessionPresenceRequest $request
     * @param int $session_id
     * @return Response
     */
    public function markCoursePresence(ICourseSessionFormService $courseService, CourseSessionPresenceRequest $request, $session_id)
    {
        return $courseService->markCourseSessionPresence($request, $session_id);
    }


    /**
     * Get presence reports for a session
     *
     * @urlParam session_id required The ID of the session to show presence report.
     *
     * @param ICourseSessionFormService $courseService
     * @param Request $request
     * @param int $session_id
     * @return Response
     */
    public function getCoursePresenceReport(ICourseSessionFormService $courseService, Request $request, $session_id)
    {
        return $courseService->getCourseSessionPresenceReport($request, $session_id);
    }
}
