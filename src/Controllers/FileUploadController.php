<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Larapress\CRUD\CRUDControllers\BaseCRUDController;
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
                'upload' => [
                    'methods' => ['POST'],
                    'url' => config('larapress.ecommerce.routes.file_uploads.name'),
                    'uses' => '\\'.self::class.'@receiveUpload',
                ]
            ]
        );
    }

    /**
     * @param Request $request
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function receiveUpload(IFileUploadService $service, FileUploadRequest $request)
    {
        return $service->receiveUploaded($request, function ($file) use ($service, $request) {
            return $service->processUploadedFile($request, $file);
        });
    }
}
