<?php

namespace Larapress\ECommerce\Services\FileUpload;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Larapress\ECommerce\Models\FileUpload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Jenky\LaravelPlupload\Facades\Plupload;
use Larapress\CRUD\Base\ICRUDService;

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
     * @return FileUpload
     */
    public function processUploadedFile(FileUploadRequest $request, UploadedFile $file) {
        $mime = $file->getMimeType();
        /** @var FileUpload */
        $link = null;
        switch ($mime) {
            case 'application/image':
                $link = $this->makeLinkFromImageUpload(
                    $file,
                    $request->get('title', $file->getFilename()),
                    $mime,
                    '/images/',
                    $request->getAccess() === 'public' ? 'public' : 'local',
                    $request->getAccess(),
                );
            break;
            case 'application/video':
                $link = $this->makeLinkFromMultiPartUpload(
                    $file,
                    $request->get('title', $file->getFilename()),
                    $mime,
                    '/videos/',
                    $request->getAccess() === 'public' ? 'public' : 'local',
                    $request->getAccess(),
                );
            break;
            case 'application/pdf':
                $link = $this->makeLinkFromMultiPartUpload(
                    $file,
                    $request->get('title', $file->getFilename()),
                    $mime,
                    '/pdf/',
                    $request->getAccess() === 'public' ? 'public' : 'local',
                    $request->getAccess(),
                );
            break;
            case 'application/zip':
                $link = $this->makeLinkFromMultiPartUpload(
                    $file,
                    $request->get('title', $file->getFilename()),
                    $mime,
                    '/zip/',
                    'local',
                    'private',
                );
            break;
            // unknown mime type
            default:
             throw new AppException(AppException::ERR_INVALID_FILE_TYPE);
        }

        $processors = config('larapress.ecommerce.file-upload-processors');
        foreach ($processors as $pClass) {
            /** @var IFileUploadProcessor */
            $processor = new $pClass();
            if ($processor->shouldProcessFile($link)) {
                $processor->postProcessFile($request, $link);
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param FileUploadRequest $request
     * @param callable $onCompleted
     * @return Illuminate\Http\Response
     */
    public function receiveUploaded(FileUploadRequest $request, $onCompleted) {
        return Plupload::receive('file', function ($file) use($request, $onCompleted) {
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
		$fileName   = time() . '.' . $image->getClientOriginalExtension();
		$path = '/'.trim($location, '/').'/'.trim($fileName, '/');

		/** @var Image $img */
		$img = Image::make($image->getRealPath());
		$img->stream(); // <-- Key point
		if (Storage::disk($disk)->put($path, $img, [$disk])) {
			return FileUpload::create([
				'title' => $title,
				'mime' => $mime,
				'path' => $path,
				'filename' => $fileName,
				'storage' => $disk,
			]);
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
        $localPath = config('filesystems.disks.local.root').'/temp';
        $file = $upload->move($localPath, $filename);

        $stream = Storage::disk('local')->readStream($localPath.'/'.$filename);
		if (Storage::disk($disk)->put(trim($location, '/').'/'.$filename, $stream)) {
            $fileSize = $file->getSize();
            // remove temp file
            unlink($file->getPathname());

            return FileUpload::create([
                'uploader_id' => Auth::user()->id,
                'title' => $title,
                'mime' => $mime,
                'path' => $location,
                'filename' => $filename,
                'storage' => $disk,
                'size' => $fileSize,
                'access' => $access,
            ]);
        }

        throw new AppException(AppException::ERR_UNEXPECTED_RESULT);
	}
}
