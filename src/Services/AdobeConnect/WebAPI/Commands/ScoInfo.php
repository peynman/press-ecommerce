<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Commands;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Command;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Entities\SCO;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Converter\Converter;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StatusValidate;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\SetEntityAttributes as FillObject;

/**
 * Gets the Sco info
 *
 * More info see {@link https://helpx.adobe.com/adobe-connect/webservices/sco-info.html}
 */
class ScoInfo extends Command
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
     * @return SCO
     */
    protected function process()
    {
        $response = Converter::convert(
            $this->client->doGet([
                'action' => 'sco-info',
                'sco-id' => $this->scoId,
                'session' => $this->client->getSession()
            ])
        );
        StatusValidate::validate($response['status']);
        $sco = new SCO();
        FillObject::setAttributes($sco, $response['sco']);
        return $sco;
    }
}
