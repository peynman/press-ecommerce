<?php

namespace Larapress\ECommerce\Services\LiveStream;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

interface ILiveStreamService
{

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return boolean
     */
    public function canStartLiveStream(Request $request);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return boolean
     */
    public function canWatchLiveStream(Request $request);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return Response
     */
    public function liveStreamStarted(Request $request);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return Response
     */
    public function liveStreamEnded(Request $request);
}
