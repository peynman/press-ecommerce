<?php

namespace Larapress\ECommerce\Services\CourseSession;

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
            config('larapress.ecommerce.lms.course_file_upload_default_form_id'),
            $tags,
            function($request, $inputNames, $form, $entry) use($upload, $sessionId) {
                $newValues = $request->all($inputNames);
                $newValues['product_id'] = $sessionId;
                $newValues['user_id'] = $entry->user_id;
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
}
