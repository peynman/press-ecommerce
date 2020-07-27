<?php

namespace Larapress\ECommerce\Services\AdobeConnect;

use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\CRUD\Events\CRUDVerbEvent;
use Larapress\CRUD\Services\IReportSource;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\Services\AdobeConnect\IAdobeConnectService;
use Larapress\ECommerce\Services\Banking\Events\CartPurchasedEvent;
use Larapress\Profiles\Models\Filter;
use Larapress\Reports\Services\BaseReportSource;
use Larapress\Reports\Services\IReportsService;

class SyncACMeetingOnProductEvent
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
            $this->service->createMeetingForProduct($event->model);
        }
    }
}
