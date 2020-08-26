<?php

namespace Larapress\ECommerce\Services\CourseSession;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\ECommerce\Models\Product;
use Larapress\Profiles\Services\IFormEntryService;

class CourseSessionFormService implements ICourseSessionFormService {
    /**
     * Undocumented function
     *
     * @param SessionFormRequest $request
     * @param int $sessionId
     * @param FileUpload|null $upload
     * @return void
     */
    public function receiveCourseForm(CourseSessionFormRequest $request, $sessionId, $upload) {
        $session = Product::with('types')->find($sessionId);
        if (is_null($session)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        $tags = 'course-'.$sessionId.'-taklif';
        $request->title = trans('larapress::ecommerce.products.courses.send_form_title', [
            'session_id' => $sessionId
        ]);
        /** @var IFormEntryService */
        $formService = app(IFormEntryService::class);
        $formService->updateFormEntry(
            $request,
            Auth::user(),
            config('larapress.ecommerce.lms.course_file_upload_default_form_id'),
            $tags,
            function($request, $inputNames, $form, $entry) use($upload, $sessionId, $session) {
                $newValues = $request->all($inputNames);
                $newValues['product_id'] = $sessionId;
                $newValues['product'] = [
                    'name' => $session->name,
                    'title' => $session->data['title'],
                ];
                if (isset($entry->data['values']['file_ids'])) {
                    $newValues['file_ids'] = $entry->data['values']['file_ids'];
                } else {
                    $newValues['file_ids'] = [];
                }
                $newValues['file_ids'][] = $upload->id;
                return $newValues;
            }
        );
    }

    /**
     * Undocumented function
     *
     * @param CourseSessionPresenceRequest $request
     * @param int $sessionId
     * @return void
     */
    public function markCourseSessionPresence(CourseSessionPresenceRequest $request, $sessionId) {
        $duration = intval($request->getDuration());
        $this->addCourseSessionPresenceMarkForSession(
            $request,
            Auth::user(),
            $sessionId,
            $duration,
            Carbon::now()
        );
    }

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
    public function addCourseSessionPresenceMarkForSession($request, $user, $sessionId, $duration, $at) {
        $session = Product::with('types')->find($sessionId);
        if (is_null($session)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        $tags = 'course-'.$sessionId.'-presence';
        /** @var IFormEntryService */
        $formService = app(IFormEntryService::class);
        $formService->updateFormEntry(
            $request,
            $user,
            config('larapress.ecommerce.lms.course_presense_default_form_id'),
            $tags,
            function($request, $inputNames, $form, $entry) use($sessionId, $duration, $at) {
                $newValues = !is_null($request) ? $request->all($inputNames):[];
                $newValues['product_id'] = $sessionId;
                if (isset($entry->data['values']['sessions'])) {
                    $newValues['sessions'] = $entry->data['values']['sessions'];
                } else {
                    $newValues['sessions'] = [];
                }
                $newValues['duration'] = (isset($entry->data['values']['duration']) ? intval($entry->data['values']['duration']) : 0) +
                                                 $duration;
                $newValues['sessions'][] = ['at' => $at, 'duration' => $duration];
                return $newValues;
            }
        );
    }
}
