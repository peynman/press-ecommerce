<?php

namespace Larapress\ECommerce\Services\CourseSession;

use Carbon\Carbon;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Repositories\ProductRepository;
use Larapress\Profiles\Models\FormEntry;

class CourseSessionRepository extends ProductRepository
    implements ICourseSessionRepository
{
    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @return Product[]
     */
    public function getTodayCourseSessions($user) {
        $query = $this->getPurchasedProductsPaginatedQuery(
            $user,
            0,
            [],
            ['session']
        );
        $query->whereRaw("DATEDIFF(DATE_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(data, '$.types.session.start_at')), '%Y/%m/%dT%H:%i:%s'), '".Carbon::now()->format('Y/m/d')."') = 0");

        $query->with([
            'parent',
            'children',
            'children.types',
            'children.categories',
        ]);
        $items = $query->get();
        foreach ($items as $item) {
            $item['available'] = true;

            if (isset($item->data['types']['session']['sendForm']) && $item->data['types']['session']['sendForm']) {
                $child['sent_forms'] = FormEntry::query()
                                            ->where('user_id', $user->id)
                                            ->where('form_id', config('larapress.ecommerce.lms.course_file_upload_default_form_id'))
                                            ->where('tags', 'course-'.$item->id.'-taklif')
                                            ->first();
            }

            if (isset($item['children'])) {
                foreach($item['children'] as $child) {
                    $child['available'] = true;
                }
            }
        }

        return $items;
    }


    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @return Product[]
     */
    public function getWeekCourseSessions($user) {
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $query = $this->getPurchasedProductsPaginatedQuery(
            $user,
            0,
            [],
            ['session']
        );
        $query->whereRaw("DATEDIFF(DATE_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(data, '$.types.session.start_at')), '%Y/%m/%dT%H:%i:%s'), '".$weekStart->format('Y/m/d')."') >= 0");
        $query->whereRaw("DATEDIFF(DATE_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(data, '$.types.session.start_at')), '%Y/%m/%dT%H:%i:%s'), '".$weekEnd->format('Y/m/d')."') <= 7");

        $query->with([
            'parent',
            'children',
            'children.types',
            'children.categories',
        ]);
        $items = $query->get();
        foreach ($items as $item) {
            $item['available'] = true;

            if (isset($item->data['types']['session']['sendForm']) && isset($item->data['types']['session']['sendForm'])) {
                $child['sent_forms'] = FormEntry::query()
                                            ->where('user_id', $user->id)
                                            ->where('form_id', config('larapress.ecommerce.lms.course_file_upload_default_form_id'))
                                            ->where('tags', 'course-'.$item->id.'-taklif')
                                            ->first();
            }

            if (isset($item['children'])) {
                foreach($item['children'] as $child) {
                    $child['available'] = true;
                }
            }
        }

        return $items;
    }
}
