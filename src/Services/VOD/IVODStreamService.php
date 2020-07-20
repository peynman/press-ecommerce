<?php

namespace Larapress\ECommerce\Services\VOD;

use Illuminate\Http\Request;
use Larapress\ECommerce\Models\FileUpload;

interface IVODStreamService {

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int|FileUpload $link
     * @param string|null $stream
     * @return void
     */
    public function streamPublicLink(Request $request, $link, $stream = null);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param FileUpload $link
     * @param string|null $stream
	 * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function stream(Request $request, FileUpload $link, $stream = null);
}
