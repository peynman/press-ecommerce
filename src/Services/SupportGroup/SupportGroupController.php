<?php

namespace Larapress\ECommerce\Services\SupportGroup;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\Services\SupportGroup\ISupportGroupService;
use Larapress\ECommerce\Services\SupportGroup\SupportGroupUpdateRequest;

class SupportGroupController extends Controller
{
    public static function registerRoutes()
    {
        Route::any('support-group/update', '\\' . self::class . '@updateSupportGroups')
            ->name('users.edit.support-group');
    }

    /**
     * @param Request $request
     *
     * @return Response
     */
    public function updateSupportGroups(ISupportGroupService $service, SupportGroupUpdateRequest $request)
    {
        return $service->updateUsersSupportGroup($request);
    }
}
