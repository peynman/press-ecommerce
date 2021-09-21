<?php

namespace Larapress\ECommerce\Controllers;

use Larapress\CRUD\Services\CRUD\CRUDController;
use Larapress\ECommerce\Services\GiftCodes\IGiftCodeService;
use Illuminate\Http\Response;
use Larapress\ECommerce\Services\GiftCodes\GiftCodeCloneRequest;

/**
 * @group Gift Code Management
 */
class GiftCodeController extends CRUDController
{
    /**
     * Clone Gift Code

     * @return Response
     */
    public function cloneGiftCode(IGiftCodeService $service, GiftCodeCloneRequest $request)
    {
        return $service->cloneGiftCode($request->getGiftCode(), $request->getCloneCount());
    }
}
