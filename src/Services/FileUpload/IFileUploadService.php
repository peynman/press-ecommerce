<?php

namespace Larapress\Ecommerce\Services\FileUpload;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Larapress\CRUD\Services\ICRUDService;
use Larapress\ECommerce\Models\FileUpload;

interface IFileUploadService {
    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return string
     */
    public function getUploadLocalPath(FileUpload $upload);

        /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return string
     */
    public function getUploadLocalDir(FileUpload $upload);

    /**
     * Undocumented function
     *
     * @param UploadedFile $file
     * @return FileUpload
     */
    public function processUploadedFile(FileUploadRequest $request, UploadedFile $file, $existingId = null);

    /**
     * Undocumented function
     *
     * @param FileUploadRequest $request
     * @param callable $onCompleted
     * @return Response
     */
    public function receiveUploaded(FileUploadRequest $request, $onCompleted, $existingId = null);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $fileId
     * @return void
     */
    public function serveFile(Request $request, $fileId);


    /**
     * Undocumented function
     *
     * @param string $encoded
     * @param string $storage
     * @param string $folder
     * @return bool
     */
    public function saveBase64Image($encoded, $storage, $folder);
}
