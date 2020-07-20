<?php

namespace Larapress\ECommerce\Services\VOD;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Product\IProductService;

class VODStreamController extends Controller
{
    public static function registerPublicWebRoutes()
    {
        Route::get('vod/public/{file_id}/stream', '\\' . self::class . '@vodPublicStream')
            ->name('vod.any.public.stream');
        Route::get('vod/public/{file_id}/{path}', '\\' . self::class . '@vodPublicStream')
            ->name('vod.any.public.stream.parts')->where('path', '.*');

        Route::get('vod/{product_id}/link/{file_id}/stream', '\\' . self::class . '@vodStream')
            ->name('vod.any.playback.stream');
        Route::get('vod/{product_id}/link/{file_id}/{path}', '\\' . self::class . '@vodStream')
            ->name('vod.any.playback.stream.parts')->where('path', '.*');
    }

    /**
     * Undocumented function
     *
     * @param IProductService $service
     * @param IVODStreamService $streamer
     * @param Request $request
     * @param int $product_id
     * @param int $file_id
     * @param string|null $streamPath
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function vodStream(IProductService $service, IVODStreamService $streamer, Request $request, $product_id, $file_id, $streamPath = null)
    {
        return $service->checkProductLinkAccess(
            $request,
            $product_id,
            $file_id,
            // on access available
            function (Request $request, Product $product, FileUpload $link) use ($streamer, $streamPath) {
                return $streamer->stream($request, $link, $streamPath);
            }
        );
    }


    /**
     * Undocumented function
     *
     * @param IProductService $service
     * @param IVODStreamService $streamer
     * @param Request $request
     * @param int $product_id
     * @param int $file_id
     * @param string|null $streamPath
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function vodPublicStream(IVODStreamService $streamer, Request $request, $file_id, $streamPath = null)
    {
        return $streamer->streamPublicLink($request, $file_id, $streamPath);
    }
}
