<?php

namespace Larapress\ECommerce\Services\AdobeConnect;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\Services\AdobeConnect\IAdobeConnectService;

class AdobeConnectController extends Controller
{
    public static function registerRoutes()
    {
        Route::post(
            '/adobe-connect/verify/{session_id}',
            '\\'.self::class.'@verifyAdobeConnectMeeting'
        )->name('adobe-connect.any.verify');
    }

    public function verifyAdobeConnectMeeting(IAdobeConnectService $service, $session_id)
    {
        return $service->verifyProductMeeting(Auth::user(), $session_id);
    }
}
