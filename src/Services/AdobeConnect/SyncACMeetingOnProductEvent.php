<?php

namespace Larapress\ECommerce\Services\AdobeConnect;

use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\CRUD\Events\CRUDVerbEvent;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\Services\AdobeConnect\IAdobeConnectService;

class SyncACMeetingOnProductEvent implements ShouldQueue
{
    /** @var IAdobeConnectService */
    private $service;

    public function __construct(IAdobeConnectService $service)
    {
        $this->service = $service;
    }

    public function handle(CRUDVerbEvent $event)
    {
        if ($event->providerClass === ProductCRUDProvider::class) {
            $model = $event->getModel();
            if (isset($model->data['types']['ac_meeting']['servers'])) {
                $this->service->createMeetingForProduct($model);
            }
        }
    }
}
