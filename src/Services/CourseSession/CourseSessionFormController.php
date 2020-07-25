<?php

namespace Larapress\ECommerce\Services\CourseSession;

use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\Ecommerce\Services\FileUpload\IFileUploadService;

class CourseSessionFormController extends Controller
{
    public static function registerPublicApiRoutes()
    {
        Route::post('course-session/{session_id}/upload-form', '\\' . self::class . '@receiveCourseForm')
            ->name('course-sessions.any.upload-form');
    }

    /**
     * Undocumented function
     *
     * @param ICourseSessionFormService $courseService
     * @param IFileUploadService $service
     * @param CourseSessionFormRequest $request
     * @param [type] $session_id
     * @return void
     */
    public function receiveCourseForm(ICourseSessionFormService $courseService, IFileUploadService $service, CourseSessionFormRequest $request, $session_id)
    {
        return $service->receiveUploaded($request, function (UploadedFile $file) use ($request, $courseService, $service, $session_id) {
            $upload = $service->processUploadedFile($request, $file);
            return $courseService->receiveCourseForm($request, $session_id, $upload);
        });
    }
}
