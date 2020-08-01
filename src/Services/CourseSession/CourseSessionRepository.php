<?php

namespace Larapress\ECommerce\Services\CourseSession;

use Carbon\Carbon;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Repositories\ProductRepository;

class CourseSessionRepository extends ProductRepository
    implements ICourseSessionRepository
{
    /**
     * Undocumented function
     *
     * @param SessionFormRequest $request
     * @param int $sessionId
     * @param FileUpload|null $upload
     * @return void
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
            if (isset($item['children'])) {
                foreach($item['children'] as $child) {
                    $child['available'] = true;
                }
            }
        }

        return $items;
    }
}
