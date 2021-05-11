<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Request;
use Larapress\CRUD\Services\CRUD\BaseCRUDController;
use Larapress\ECommerce\CRUD\GiftCodeCRUDProvider;
use Larapress\ECommerce\Services\GiftCodes\GiftCodeCloneRequest;
use Larapress\ECommerce\Services\GiftCodes\IGiftCodeService;
use Illuminate\Http\Response;

/**
 * Standard CRUD Controller for Gift Code resource.
 *
 * @group Gift Code Management
 */
class GiftCodeController extends BaseCRUDController
{
    public static function registerRoutes()
    {
        parent::registerCrudRoutes(
            config('larapress.ecommerce.routes.gift_codes.name'),
            self::class,
            GiftCodeCRUDProvider::class,
            [
                'create.clone' => [
                    'methods' => ['POST'],
                    'uses' => '\\'.self::class.'@cloneGiftCode',
                    'url' => config('larapress.ecommerce.routes.gift_codes.name').'/clone',
                ]
            ]
        );
    }

    /**
     * Clone Gift Code

     * @return Response
     */
    public function cloneGiftCode(IGiftCodeService $service, GiftCodeCloneRequest $request)
    {
        return $service->cloneGiftCode($request->getGiftCode(), $request->getCloneCount());
    }
}
