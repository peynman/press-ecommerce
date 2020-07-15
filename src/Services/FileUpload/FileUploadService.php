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
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;

class FileUploadService implements IFileUploadService {
    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return string
     */
    public function getUploadLocalPath(FileUpload $upload) {
        return config('filesystems.disks')[$upload->storage]['root'].'/'.$upload->path.'/'.$upload->filename;
    }

    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return string
     */
    public function getUploadLocalDir(FileUpload $upload) {
        return config('filesystems.disks')[$upload->storage]['root'].'/'.$upload->path.'/'.$upload->filename;
    }

    /**
     * Undocumented function
     *
     * @param UploadedFile $file
     * @return FileUpload|null
     */
    public function processUploadedFile(FileUploadRequest $request, UploadedFile $file) {
        $mime = $file->getClientOriginalExtension();
        /** @var FileUpload */
        $link = null;
        switch ($mime) {
            case 'png':
            case 'jpeg':
            case 'jpg':
                    $link = $this->makeLinkFromImageUpload(
                    $file,
                    $request->get('title', $file->getFilename()),
                    'image/'.$mime,
                    '/images/',
                    $request->getAccess() === 'public' ? 'public' : 'local',
                    $request->getAccess(),
                );
            break;
            case 'mp4':
                $link = $this->makeLinkFromMultiPartUpload(
                    $file,
                    $request->get('title', $file->getFilename()),
                    'video/'.$mime,
                    '/videos/',
                    $request->getAccess() === 'public' ? 'public' : 'local',
                    $request->getAccess(),
                );
            break;
            case 'pdf':
                $link = $this->makeLinkFromMultiPartUpload(
                    $file,
                    $request->get('title', $file->getFilename()),
                    'application/'.$mime,
                    '/pdf/',
                    $request->getAccess() === 'public' ? 'public' : 'local',
                    $request->getAccess(),
                );
            break;
            case 'zip':
                $link = $this->makeLinkFromMultiPartUpload(
                    $file,
                    $request->get('title', $file->getFilename()),
                    'application/'.$mime,
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
    public function receiveUploaded(FileUploadRequest $request, $onCompleted) {
        $uploader = new Plupload($request, app(Filesystem::class));
        return $uploader->process('file', function ($file) use($request, $onCompleted) {
            return $onCompleted($file);
		});
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
	protected function makeLinkFromImageUpload($upload, $title, $mime, $location, $disk = 'local', $access = 'private') {
		$image      = $upload;
		$fileName   = Helpers::randomString(10) . '.' . $image->getClientOriginalExtension();
		$path = '/'.trim($location, '/').'/'.trim($fileName, '/');

		/** @var Image $img */
		$img = Image::make($image->getRealPath());
		$img->stream(); // <-- Key point
		if (Storage::disk($disk)->put($path, $img, [$disk])) {
            $fileSize = $upload->getSize();
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

            CRUDCreated::dispatch($fileUpload, \Larapress\ECommerce\CRUD\FileUploadCRUDProvider::class, Carbon::now());
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
	protected function makeLinkFromMultiPartUpload($upload, $title, $mime, $location, $disk = 'local', $access = 'private') {
        $filename   = time().'.'.Helpers::randomString(10).'.'.$upload->getClientOriginalExtension();
        $stream = Storage::disk('local')->readStream('plupload/'.$upload->getFilename());
		if (Storage::disk($disk)->put(trim($location, '/').'/'.$filename, $stream)) {
            $fileSize = $upload->getSize();
            $fileUpload = FileUpload::create([
                'uploader_id' => Auth::user()->id,
                'title' => $title,
                'mime' => $mime,
                'path' => trim($location, '/').'/'.$filename,
                'filename' => $filename,
                'storage' => $disk,
                'size' => $fileSize,
                'access' => $access,
            ]);

            CRUDCreated::dispatch($fileUpload, \Larapress\ECommerce\CRUD\FileUploadCRUDProvider::class, Carbon::now());
            return $fileUpload;
        }

        throw new AppException(AppException::ERR_UNEXPECTED_RESULT);
	}
}
