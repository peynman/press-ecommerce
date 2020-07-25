<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Commands;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Command;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Converter\Converter;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StatusValidate;

/**
 * Remove one principal, either user or group.
 *
 * More info see {@link https://helpx.adobe.com/adobe-connect/webservices/principals-delete.html}
 */
class PrincipalDelete extends Command
{
    /**
     * @var int
     */
    protected $principalId;

    /**
     * @param int $principalId
     */
    public function __construct($principalId)
    {
        $this->principalId = $principalId;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    protected function process()
    {
        $response = Converter::convert(
            $this->client->doGet([
                'action' => 'principals-delete',
                'principal-id' => $this->principalId,
                'session' => $this->client->getSession()
            ])
        );

        StatusValidate::validate($response['status']);

        return true;
    }
}
