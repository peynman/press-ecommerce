<?php

namespace Larapress\ECommerce\Services\AdobeConnect;

use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\CRUD\Events\CRUDVerbEvent;
use Larapress\CRUD\Services\IReportSource;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\Services\AdobeConnect\IAdobeConnectService;
use Larapress\ECommerce\Services\Banking\Events\CartPurchasedEvent;
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
            $this->createMeetingForProduct($event->model);
        }
    }

    protected function createMeetingForProduct($item)
    {
        $types = $item->types;
        foreach ($types as $type) {
            if ($type->name === 'ac_meeting') {
                $meetingName = isset($item->data['types']['ac_meeting']['meeting_name']) && !empty($item->data['types']['ac_meeting']['meeting_name']) ?
                    $item->data['types']['ac_meeting']['meeting_name'] : 'ac-product-' . $item->id;
                $meetingFolder = isset($item->data['types']['ac_meeting']['meeting_folder']) && !empty($item->data['types']['ac_meeting']['meeting_folder']) ?
                    $item->data['types']['ac_meeting']['meeting_folder'] : 'meetings';
                $this->service->connect(
                    $item->data['types']['ac_meeting']['server'],
                    $item->data['types']['ac_meeting']['username'],
                    $item->data['types']['ac_meeting']['password']
                );
                $this->service->createMeeting(
                    $meetingFolder,
                    $meetingName
                );

                if (isset($item->data['types']['ac_meeting']['status'])) {
                    $status = $item->data['types']['ac_meeting']['status'];
                    if ($status === 'migrate') {

                    }
                }
            }
        }
    }
}
