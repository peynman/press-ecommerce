<?php

namespace Larapress\Ecommerce\Services\FileUpload;

use Illuminate\Http\UploadedFile;
use Larapress\CRUD\Base\ICRUDService;
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
    public function processUploadedFile(FileUploadRequest $request, UploadedFile $file);

    /**
     * Undocumented function
     *
     * @param FileUploadRequest $request
     * @param callable $onCompleted
     * @return Response
     */
    public function receiveUploaded(FileUploadRequest $request, $onCompleted);
}
