<?php

namespace Larapress\ECommerce\Services\CourseSession;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\ECommerce\Models\Product;
use Larapress\Profiles\Services\FormEntry\IFormEntryService;
use Larapress\CRUD\Services\ICRUDProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Larapress\CRUD\Services\ICRUDService;
use Larapress\ECommerce\CRUD\FileUploadCRUDProvider;
use Larapress\ECommerce\IECommerceUser;
use Larapress\Ecommerce\Services\FileUpload\IFileUploadService;
use Larapress\Profiles\Models\FormEntry;

class CourseSessionFormService implements
    ICourseSessionFormService
{
    /**
     * Undocumented function
     *
     * @param SessionFormRequest $request
     * @param int $sessionId
     * @param FileUpload|null $upload
     * @return void
     */
    public function receiveCourseForm(CourseSessionFormRequest $request, $sessionId, $upload)
    {
        $session = Product::with('types')->find($sessionId);
        if (is_null($session)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        $tags = 'course-' . $sessionId . '-taklif';
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
            function ($request, $inputNames, $form, $entry) use ($upload, $sessionId, $session) {
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
     * @param Request $request
     * @param int $sessionId
     * @param int $entryId
     * @param int $fileId
     * @return Response
     */
    public function serveSessionFormFile($request, $sessionId, $entryId, $fileId)
    {
        $session = Product::with('types')->find($sessionId);
        if (is_null($session)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        /** @var FormEntry $entry */
        $entry = FormEntry::find($entryId);

        if (is_null($entry)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        if ($entry->tags !== 'course-' . $sessionId . '-taklif') {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }

        if (!isset($entry->data['values']['file_ids']) || !in_array($fileId, $entry->data['values']['file_ids'])) {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }

        $file = FileUpload::find($fileId);
        if (is_null($file)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        /** @var IECommerceUser */
        $user = Auth::user();

        if (is_null($user)) {
            throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
        }

        if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            if ($user->hasRole(config('larapress.ecommerce.lms.owner_role_id'))) {
                if (!in_array($sessionId, $user->getOwenedProductsIds())) {
                    throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
                }
            } else {
                $provider = new FileUploadCRUDProvider();
                if (!$provider->onBeforeAccess($file)) {
                    throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
                }
            }
        }

        /** @var IFileUploadService $fileService */
        $fileService = app(IFileUploadService::class);

        return $fileService->serveFile($request, $file, false);
    }

    /**
     * Undocumented function
     *
     * @param CourseSessionPresenceRequest $request
     * @param int $sessionId
     * @return mixed
     */
    public function markCourseSessionPresence(CourseSessionPresenceRequest $request, $sessionId)
    {
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
     * @param int $sessionId
     * @return array
     */
    public function getCourseSessionPresenceReport(Request $request, $sessionId)
    {
        /** @var Product */
        $session = Product::with('types')->find($sessionId);
        if (is_null($session)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        $productIds = [$sessionId];
        if (!is_null($session->parent_id)) {
            $productIds[] = $session->parent_id;
        }

        $userProviderClass = config('larapress.crud.user.crud-provider');
        /** @var ICRUDService */
        $crud = app(ICRUDService::class);
        /** @var ICRUDProvider */
        $provider = new $userProviderClass();
        /** @var Builder */
        $crud->useProvider($provider);
        $filters = $request->get('filters', []);

        [$query, $total] = $crud->buildQueryForRequest($request, function ($query) use ($request, $productIds, $sessionId, $filters) {
            if (isset($filters['presence']) && $filters['presence'] === 'presence') {
                $query->whereHas('form_entries', function ($q) use ($sessionId) {
                    $q->where('tags', 'course-' . $sessionId . '-presence');
                });
            } else {
                $query->where(function ($query) use ($productIds, $sessionId) {
                    $query->orWhereHas('purchases', function ($q) use ($productIds) {
                        $q->whereHas('products', function ($q) use ($productIds) {
                            $q->whereIn('id', $productIds);
                        });
                    });
                    $query->orWhereHas('form_entries', function ($q) use ($sessionId) {
                        $q->where('tags', 'course-' . $sessionId . '-presence');
                    });
                });

                if (isset($filters['presence']) && $filters['presence'] === 'absent') {
                    $query->whereDoesntHave('form_entries', function ($q) use ($sessionId) {
                        $q->where('tags', 'course-' . $sessionId . '-presence');
                    });
                }
            }

            $query->with([
                'form_entries' => function ($q) use ($sessionId) {
                    $q->where('tags', 'course-' . $sessionId . '-presence');
                },
            ]);
        });

        $models = $query->get();
        if ($total === -1) {
            $total = $models->count();
        }

        return [
            'data' => $models,
            'total' => $total,
            'from' => ($request->get('page', 1) - 1) * $request->get('limit', 10),
            'to' => $request->get('page', 1) * $request->get('limit', 10),
            'current_page' => $request->get('page', 0),
            'per_page' => $request->get('limit', 10),
            'ref_id' => $request->get('ref_id'),
        ];
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
    public function addCourseSessionPresenceMarkForSession($request, $user, $sessionId, $duration, $at)
    {
        $session = Product::with('types')->find($sessionId);
        if (is_null($session)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        $tags = 'course-' . $sessionId . '-presence';
        /** @var IFormEntryService */
        $formService = app(IFormEntryService::class);
        $formService->updateFormEntry(
            $request,
            $user,
            config('larapress.ecommerce.lms.course_presense_default_form_id'),
            $tags,
            function ($request, $inputNames, $form, $entry) use ($sessionId, $duration, $at) {
                $newValues = !is_null($request) ? $request->all($inputNames) : [];
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
