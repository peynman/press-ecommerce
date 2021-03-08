<?php

namespace Larapress\ECommerce\Services\VOD;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Profiles\Repository\Domain\IDomainRepository;
use Illuminate\Support\Str;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\Models\FileUpload;

class VODStreamService implements IVODStreamService
{

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int|FileUpload $link
     * @param string|null $stream
     * @return void
     */
    public function streamPublicLink(Request $request, $link, $stream = null)
    {
        if (is_numeric($link)) {
            /** @var FileUpload */
            $link = FileUpload::find($link);
        }

        if ($link->access === 'public') {
            return $this->stream($request, $link, $stream);
        }

        throw new AppException(AppException::ERR_INVALID_QUERY);
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param FileUpload $link
     * @param string|null $stream
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function stream(Request $request, FileUpload $link, $stream = null)
    {
        if (is_null($stream)) {
            return response()->stream(function () use ($link) {
                $fileStream = self::getVODMasterHLSPlayListFile($link);
                fpassthru($fileStream);
                if (is_resource($fileStream)) {
                    fclose($fileStream);
                }
            }, 200, [
                'Content-Type' => 'application/x-mpegURL',
            ]);
        }

        if (Str::endsWith($stream, '.m3u8')) {
            return response()->stream(function () use ($link, $stream) {
                $fileStream = self::getVODFileForHLSVariant($link, $stream);
                fpassthru($fileStream);
                if (is_resource($fileStream)) {
                    fclose($fileStream);
                }
            }, 200, [
                'Content-Type' => 'application/x-mpegURL',
            ]);
        } elseif (Str::endsWith($stream, ".key")) {
            return response()->download(self::getVODFileForHLSVariant($link, $stream), null, [
                'Content-Type' => 'application/txt',
            ]);
        } elseif (Str::endsWith($stream, ".ts")) {
            return response()->stream(function () use ($link, $stream) {
                $fileStream = self::getVODFileForHLSVariant($link, $stream);
                fpassthru($fileStream);
                if (is_resource($fileStream)) {
                    fclose($fileStream);
                }
            }, 200, [
                'Content-Type' => 'video/MP2T',
            ]);
        } else {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }
    }

    /**
     * @param FileUpload $link
     *
     * @return null|resource
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function getVODMasterHLSPlayListFile(FileUpload $link)
    {
        $hlsFolder = Helpers::getPathWithoutExtension($link->path);

        return Storage::disk($link->storage)->readStream($hlsFolder.'/vod.m3u8');
    }

    /**
     * @param $link
     * @param $variant
     *
     * @return null|resource
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function getVODFileForHLSVariant($link, $variant)
    {
        $hlsFolder = Helpers::getPathWithoutExtension($link->path);
        return Storage::disk($link->storage)->readStream($hlsFolder.'/'.$variant);
    }
}
