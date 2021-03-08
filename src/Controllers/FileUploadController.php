<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\CRUD\Middleware\CRUDAuthorizeRequest;
use Larapress\ECommerce\CRUD\FileUploadCRUDProvider;
use Larapress\ECommerce\Services\FileUpload\FileUploadRequest;
use Larapress\Ecommerce\Services\FileUpload\IFileUploadService;

class FileUploadController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.file_uploads.name'),
            self::class,
            FileUploadCRUDProvider::class,
            [
                'upload.update' => [
                    'methods' => ['POST'],
                    'url' => config('larapress.ecommerce.routes.file_uploads.name').'/{file_id}',
                    'uses' => '\\'.self::class.'@overwriteUpload',
                ],
                'upload' => [
                    'methods' => ['POST'],
                    'url' => config('larapress.ecommerce.routes.file_uploads.name'),
                    'uses' => '\\'.self::class.'@receiveUpload',
                ],
            ]
        );
    }

    public static function registerWebRoutes()
    {
        Route::get(config('larapress.ecommerce.routes.file_uploads.name').'/download/{file_id}', '\\'.self::class.'@downloadFile')
            ->middleware(CRUDAuthorizeRequest::class)
            ->name(config('larapress.ecommerce.routes.file_uploads.name').'.view.download');
    }

    /**
     * @param IFileUploadService $service
     * @param FileUploadRequest $request
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function receiveUpload(IFileUploadService $service, FileUploadRequest $request)
    {
        return $service->receiveUploaded($request, function ($file) use ($service, $request) {
            return $service->processUploadedFile($request, $file);
        });
    }

    /**
     * Undocumented function
     *
     * @param IFileUploadService $service
     * @param FileUploadRequest $request
     * @param int $file_id
     * @return \Illuminate\Http\Response
     */
    public function overwriteUpload(IFileUploadService $service, FileUploadRequest $request, $file_id)
    {
        return $service->receiveUploaded($request, function ($file) use ($service, $request) {
            return $service->processUploadedFile($request, $file);
        });
    }

    /**
     * Undocumented function
     *
     * @param IFileUploadService $service
     * @param FileUploadRequest $request
     * @param int $file_id
     * @return \Illuminate\Http\Response
     */
    public function downloadFile(IFileUploadService $service, Request $request, $file_id)
    {
        return $service->serveFile($request, $file_id);
    }
}
