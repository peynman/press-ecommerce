<?php

namespace Larapress\ECommerce\Services\AdobeConnect;

use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Services\IReportSource;
use Larapress\ECommerce\Services\AdobeConnect\IAdobeConnectService;
use Larapress\ECommerce\Services\Banking\Events\CartPurchasedEvent;
use Larapress\Reports\Services\BaseReportSource;
use Larapress\Reports\Services\IReportsService;

class SyncACMeetingOnCartPurchase
{

    /** @var IAdobeConnectService */
    private $service;

    public function __construct(IAdobeConnectService $service)
    {
        $this->service = $service;
    }

    public function handle(CartPurchasedEvent $event)
    {
        $this->addACProductsParticipantsToMeeting($event->cart->products, $event->cart->user);
    }

    protected function addACProductsParticipantsToMeeting($products, $user)
    {
        foreach ($products as $item) {
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
                    $this->service->addParticipantToMeeting(
                        $meetingFolder,
                        $meetingName,
                        $user
                    );
                }
            }

            $children = $item->children;
            if (count($children) > 0) {
                $this->addACProductsParticipantsToMeeting(
                    $children,
                    $user
                );
            }
        }
    }
}
