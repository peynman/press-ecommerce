<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Commands;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Command;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Converter\Converter;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StatusValidate;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Entities\Principal;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\SetEntityAttributes as FillObject;

/**
 * Provides information about one principal, either a user or a group.
 *
 * More info see {@link https://helpx.adobe.com/adobe-connect/webservices/principal-info.html}
 */
class ReportMeetingSessions extends Command
{
    /**
     * @var int
     */
    protected $scoId;

    /**
     * @param int $scoId
     */
    public function __construct($scoId)
    {
        $this->scoId = $scoId;
    }

    /**
     * @inheritdoc
     *
     * @return Principal
     */
    protected function process()
    {
        $response = Converter::convert(
            $this->client->doGet([
                'action' => 'report-meeting-sessions',
                'sco-id' => $this->scoId,
                'sort-version' => 'desc',
                'session' => $this->client->getSession()
            ])
        );

        StatusValidate::validate($response['status']);

        return $response;
    }
}
