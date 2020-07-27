<?php

namespace Larapress\ECommerce\Services\FileUpload;

use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Larapress\ECommerce\Models\FileUpload;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Larapress\CRUD\Events\CRUDCreated;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\CRUD\Events\CRUDVerbEvent;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\CRUD\FileUploadCRUDProvider;

class FileUploadService implements IFileUploadService
{
    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return string
     */
    public function getUploadLocalPath(FileUpload $upload)
    {
        return config('filesystems.disks')[$upload->storage]['root'] . '/' . $upload->path . '/' . $upload->filename;
    }

    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return string
     */
    public function getUploadLocalDir(FileUpload $upload)
    {
        return config('filesystems.disks')[$upload->storage]['root'] . '/' . $upload->path . '/' . $upload->filename;
    }

    /**
     * Undocumented function
     *
     * @param UploadedFile $file
     * @return FileUpload|null
     */
    public function processUploadedFile(FileUploadRequest $request, UploadedFile $file, $existingId = null)
    {
        $existing = null;
        if (!is_null($existingId)) {
            $existing = FileUpload::find($existingId);

            if (is_null($existing)) {
                throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
            }
        }
        $mime = $file->getClientOriginalExtension();
        /** @var FileUpload */
        $link = null;
        switch ($mime) {
            case 'png':
            case 'jpeg':
            case 'jpg':
                $link = $this->makeLinkFromImageUpload(
                    $file,
                    $existing,
                    $request->get('title', $file->getFilename()),
                    'image/' . $mime,
                    '/images/',
                    $request->getAccess() === 'public' ? 'public' : 'local',
                    $request->getAccess(),
                );
                break;
            case 'mp4':
                $link = $this->makeLinkFromMultiPartUpload(
                    $file,
                    $existing,
                    $request->get('title', $file->getFilename()),
                    'video/' . $mime,
                    '/videos/',
                    $request->getAccess() === 'public' ? 'public' : 'local',
                    $request->getAccess(),
                );
                break;
            case 'pdf':
                $link = $this->makeLinkFromMultiPartUpload(
                    $file,
                    $existing,
                    $request->get('title', $file->getFilename()),
                    'application/' . $mime,
                    '/pdf/',
                    $request->getAccess() === 'public' ? 'public' : 'local',
                    $request->getAccess(),
                );
                break;
            case 'zip':
                $link = $this->makeLinkFromMultiPartUpload(
                    $file,
                    $existing,
                    $request->get('title', $file->getFilename()),
                    'application/' . $mime,
                    '/zip/',
                    'local',
                    'private',
                );
                break;
                // unknown mime type
            default:
                throw new AppException(AppException::ERR_INVALID_FILE_TYPE);
        }

        $processors = config('larapress.ecommerce.file_upload_processors');
        foreach ($processors as $pClass) {
            /** @var IFileUploadProcessor */
            $processor = new $pClass();
            if ($processor->shouldProcessFile($link)) {
                $processor->postProcessFile($request, $link);
            }
        }

        return $link;
    }

    /**
     * Undocumented function
     *
     * @param FileUploadRequest $request
     * @param callable $onCompleted
     * @return Illuminate\Http\Response
     */
    public function receiveUploaded(FileUploadRequest $request, $onCompleted, $existingId = null)
    {
        $uploader = new Plupload($request, app(Filesystem::class));
        return $uploader->process('file', function ($file) use ($request, $onCompleted) {
            return $onCompleted($file);
        });
    }


    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $fileId
     * @return void
     */
    public function serveFile(Request $request, $fileId) {
        /** @var FileUpload */
        $link = FileUpload::find($fileId);
        if (is_null($link)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        return response()->stream(function() use($link) {
            $fileStream = Storage::disk($link->storage)->readStream($link->path);
            fpassthru($fileStream);
            if (is_resource($fileStream)) {
                fclose($fileStream);
            }
        }, 200, [
            'Content-Type' => $link->mime,
        ]);
    }

    /**
     * @param UploadedFile     $upload
     * @param string           $title
     * @param string           $mime
     * @param string           $location
     * @param string           $disk
     *
     * @return FileUpload
     * @throws AppException
     */
    protected function makeLinkFromImageUpload($upload, $existing, $title, $mime, $location, $disk = 'local', $access = 'private')
    {
        $image      = $upload;
        $fileName   = Helpers::randomString(10) . '.' . $image->getClientOriginalExtension();
        $path = '/' . trim($location, '/') . '/' . trim($fileName, '/');

        /** @var Image $img */
        $img = Image::make($image->getRealPath());
        $img->stream(); // <-- Key point
        if (Storage::disk($disk)->put($path, $img, [$disk])) {
            $fileSize = $upload->getSize();
            if (is_null($existing)) {
                $fileUpload = FileUpload::create([
                    'uploader_id' => Auth::user()->id,
                    'title' => $title,
                    'mime' => $mime,
                    'path' => $path,
                    'filename' => $fileName,
                    'storage' => $disk,
                    'access' => $access,
                    'size' => $fileSize,
                ]);

            } else {
                $fileUpload = $existing;
                $fileUpload->update([
                    'uploader_id' => Auth::user()->id,
                    'title' => $title,
                    'mime' => $mime,
                    'path' => $path,
                    'filename' => $fileName,
                    'storage' => $disk,
                    'access' => $access,
                    'size' => $fileSize,
                ]);

            }

            CRUDVerbEvent::dispatch($fileUpload, FileUploadCRUDProvider::class, Carbon::now(), 'upload');

            return $fileUpload;
        }

        throw new AppException(AppException::ERR_UNEXPECTED_RESULT);
    }


    /**
     * @param UploadedFile $upload
     * @param string       $title
     * @param string       $mime
     * @param string       $location
     * @param string       $disk
     *
     * @return FileUpload
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     * @throws AppException
     */
    protected function makeLinkFromMultiPartUpload($upload, $existing, $title, $mime, $location, $disk = 'local', $access = 'private')
    {
        $filename   = time() . '.' . Helpers::randomString(10) . '.' . $upload->getClientOriginalExtension();
        $stream = Storage::disk('local')->readStream('plupload/' . $upload->getFilename());
        if (Storage::disk($disk)->put(trim($location, '/') . '/' . $filename, $stream)) {
            $fileSize = $upload->getSize();

            if (is_null($existing)) {
                $fileUpload = FileUpload::create([
                    'uploader_id' => Auth::user()->id,
                    'title' => $title,
                    'mime' => $mime,
                    'path' => trim($location, '/') . '/' . $filename,
                    'filename' => $filename,
                    'storage' => $disk,
                    'size' => $fileSize,
                    'access' => $access,
                ]);
            } else {
                $fileUpload = $existing;
                $fileUpload->update([
                    'uploader_id' => Auth::user()->id,
                    'title' => $title,
                    'mime' => $mime,
                    'path' => trim($location, '/') . '/' . $filename,
                    'filename' => $filename,
                    'storage' => $disk,
                    'size' => $fileSize,
                    'access' => $access,
                ]);
            }

            CRUDVerbEvent::dispatch($fileUpload, FileUploadCRUDProvider::class, Carbon::now(), 'upload');

            return $fileUpload;
        }

        throw new AppException(AppException::ERR_UNEXPECTED_RESULT);
    }
}