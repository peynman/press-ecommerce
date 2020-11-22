<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\CRUDControllers\BaseCRUDController;
use Larapress\ECommerce\CRUD\GiftCodeCRUDProvider;
use Larapress\ECommerce\Services\Banking\IBankingService;

class GiftCodeController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.gift_codes.name'),
            self::class,
            GiftCodeCRUDProvider::class,
            [
                'create.duplicate' => [
                    'methods' => ['POST'],
                    'uses' => '\\'.self::class.'@duplicateGiftCode',
                    'url' => config('larapress.ecommerce.routes.gift_codes.name').'/{id}/duplicate',
                ]
            ]
        );
    }

    /**
     * Undocumented function
     *
     * @param IBankingService $service
     * @param Request $request
     * @param int $giftCodeId
     * @return void
     */
    public function duplicateGiftCode(IBankingService $service, Request $request, $giftCodeId) {
        return $service->duplicateGiftCodeForRequest($request, $giftCodeId);
    }
}
