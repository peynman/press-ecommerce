<?php

namespace Larapress\ECommerce\Services\LiveStream;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\Exceptions\AppException;

class LiveStreamController extends Controller
{

    public static function registerPublicApiRoutes()
    {
        Route::any('live-stream/auth', '\\' . self::class . '@onAuthenticate')
            ->name('live-stream.any.atuh')
            ->middleware([
                \App\Http\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Illuminate\Session\Middleware\AuthenticateSession::class,
            ]);

        Route::any('live-stream/on-publish', '\\' . self::class . '@onPublish')
            ->name('live-stream.any.on-publish');
        Route::any('live-stream/on-update', '\\' . self::class . '@onUpdate')
            ->name('live-stream.any.on-update');
        Route::any('live-stream/on-publish-done', '\\' . self::class . '@onPublishDone')
            ->name('live-stream.any.on-publish-done');
        Route::any('live-stream/on-play', '\\' . self::class . '@onPlay')
            ->name('live-stream.any.on-play');
        Route::any('live-stream/on-play-done', '\\' . self::class . '@onPlayDone')
            ->name('live-stream.any.on-play-done');
        Route::any('live-stream/on-done', '\\' . self::class . '@onDone')
            ->name('live-stream.any.on-done');
    }


    /**
     * @param Request $request
     *
     * @return Response
     */
    public function onAuthenticate(ILiveStreamService $service, Request $request)
    {
        Log::debug('auth:', [Auth::user(), $request->headers->all()]);
        if (!$service->canWatchLiveStream($request)) {
            throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
        }

        return response('ok');
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function onPublish(ILiveStreamService $service, Request $request)
    {
        Log::debug('can publish', [$request->all(), $request->headers->all()]);
        if (!$service->canStartLiveStream($request)) {
            throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
        }
        return $service->liveStreamStarted($request);
    }

    /**
     * @param Request $request
     */
    public function onPublishDone(ILiveStreamService $service, Request $request)
    {
        Log::debug('publish done', $request->all());
        return $service->liveStreamEnded($request);
    }

    /**
     * @param Request $request
     */
    public function onUpdate(Request $request)
    {
        Log::debug('publish update', $request->all());

        return response();
    }
}
