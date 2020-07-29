<?php

namespace Larapress\ECommerce\Services\PDF;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\Ecommerce\Services\FileUpload\IFileUploadService;
use Larapress\ECommerce\Services\Product\IProductService;

class PDFFileDownloadController extends Controller
{

    public static function registerWebRoutes()
    {
        Route::any('session/{session_id}/pdf/{file_id}/download', '\\' . self::class . '@downloadPDF')
            ->name('session.any.pdf.download');
    }


    /**
     * @param Request $request
     *
     * @return Response
     */
    public function downloadPDF(IProductService $service, IFileUploadService $fileService, Request $request, $session_id, $file_id)
    {
        return $service->checkProductLinkAccess(
            $request,
            $session_id,
            $file_id,
            function ($request, $product, $link) use ($fileService) {
                return $fileService->serveFile($request, $link);
            }
        );
    }
}
