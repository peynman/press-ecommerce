<?php

namespace Larapress\ECommerce\Services\CourseSession;

use Carbon\Carbon;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Repositories\ProductRepository;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\ECommerce\Services\SupportGroup\ISupportGroupService;
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
        /** @var Product[] */
        $items = $query->get();

        /** @var IBankingService */
        $service = app(IBankingService::class);
        $locked = $service->getPeriodicInstallmentsLockedProducts($user);

        foreach ($items as $item) {
            $item['available'] = true;

            $item['locked'] = in_array($item->parent_id, $locked) && !$item->isFree();

            if (isset($item->data['types']['session']['sendForm']) && $item->data['types']['session']['sendForm']) {
                $item['sent_forms'] = FormEntry::query()
                                            ->where('user_id', $user->id)
                                            ->where('form_id', config('larapress.ecommerce.lms.course_file_upload_default_form_id'))
                                            ->where('tags', 'course-'.$item->id.'-taklif')
                                            ->first();
            }

            $itemChildren = [];
            if (!is_null($item->children) && count($item->children) > 0) {
                $innerChilds = $item->children;
                foreach($innerChilds as $child) {
                    $child['available'] = true;
                    $child['locked'] = $item['locked'];
                    $itemChildren[] = $child;
                }
            }
            $item['children'] = $itemChildren;
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
        $weekStart = Carbon::now()->startOfWeek(Carbon::SATURDAY);
        $weekEnd = Carbon::now()->endOfWeek(Carbon::FRIDAY);
        $today = Carbon::now();
        if ($today->diffInDays($weekEnd) === 0) {
            $weekStart->addDays(-7);
        }

        $query = $this->getPurchasedProductsPaginatedQuery(
            $user,
            0,
            [],
            ['session']
        );
        $query->whereRaw("DATEDIFF(DATE_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(data, '$.types.session.start_at')), '%Y/%m/%dT%H:%i:%s'), '".$weekStart->format('Y/m/d\TH:i:s')."') >= 0");
        $query->whereRaw("DATEDIFF(DATE_FORMAT(JSON_UNQUOTE(JSON_EXTRACT(data, '$.types.session.start_at')), '%Y/%m/%dT%H:%i:%s'), '".$weekEnd->format('Y/m/d\TH:i:s')."') <= 0");

        $query->with([
            'parent',
            'children',
            'children.types',
            'children.categories',
        ]);
        $items = $query->get();
        foreach ($items as $item) {
            $item['available'] = true;
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
     * @return FormEntry[]
     */
    public function getIntroducedUsersList($user) {
        /** @var ISupportGroupService  */
        $service = app(ISupportGroupService::class);
        return $service->getIntroducedUsersList($user);
    }
}
