<?php

namespace Larapress\ECommerce\Services\Azmoon;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AzmoonController extends Controller
{

    public static function registerPublicAPIRoutes()
    {
        Route::post('azmoon/{product_id}/details', '\\' . self::class . '@azmoonDetails')
            ->name('azmoon.any.details');
        Route::post('azmoon/{product_id}/answer_sheet', '\\' . self::class . '@acceptAzmoonAnswerSheet')
            ->name('azmoon.any.file');
    }

    public static function registerPublicWebRoutes()
    {
        Route::get('azmoon/{product_id}/question/{index}', '\\' . self::class . '@streamAzmoonQuestionFile')
            ->name('azmoon.any.file');
        Route::get('azmoon/{product_id}/answers/{index}', '\\' . self::class . '@streamAzmoonAnswerFile')
            ->name('azmoon.any.file');
    }

    /**
     * Undocumented function
     *
     * @param IAzmoonService $service
     * @param int $productId
     * @return array
     */
    public function azmoonDetails(IAzmoonService $service, $productId)
    {
        return $service->getAzmoonDetails($productId);
    }

    /**
     * Undocumented function
     *
     * @param IAzmoonService $service
     * @param int $productId
     * @param int $index
     * @return array
     */
    public function streamAzmoonQuestionFile(IAzmoonService $service, Request $request, $productId, $index)
    {
        return $service->streamAzmoonFileAtIndex($request, $productId, $index, false);
    }

    /**
     * Undocumented function
     *
     * @param IAzmoonService $service
     * @param int $productId
     * @param int $index
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function streamAzmoonAnswerFile(IAzmoonService $service, Request $request, $productId, $index)
    {
        return $service->streamAzmoonFileAtIndex($request, $productId, $index, true);
    }

    /**
     * Undocumented function
     *
     * @param IAzmoonService $service
     * @param Request $request
     * @param int $productId
     * @return array
     */
    public function acceptAzmoonAnswerSheet(IAzmoonService $service, Request $request, $productId)
    {
        return $service->acceptAzmoonResultForUser($request, Auth::user(), $productId);
    }
}
