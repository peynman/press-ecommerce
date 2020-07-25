<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Commands;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Command;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Converter\Converter;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StatusValidate;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\ValueTransform as VT;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StringCaseTransform as SCT;

/**
 * Set a feature
 *
 * More info see {@link https://helpx.adobe.com/adobe-connect/webservices/meeting-feature-update.html}
 */
class MeetingFeatureUpdate extends Command
{
    /**
     * @var array
     */
    protected $parameters;

    /**
     * @param int $accountId
     * @param string $featureId
     * @param bool $enable
     */
    public function __construct($accountId, $featureId, $enable)
    {
        $this->parameters = [
            'action' => 'meeting-feature-update',
            'account-id' => $accountId,
            'enable' => VT::toString($enable),
        ];

        $featureId = SCT::toHyphen($featureId);

        if (mb_strpos($featureId, 'fid-') === false) {
            $featureId = 'fid-' . $featureId;
        }

        $this->parameters['feature-id'] = $featureId;
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    protected function process()
    {
        $response = Converter::convert(
            $this->client->doGet(
                $this->parameters + ['session' => $this->client->getSession()]
            )
        );
        StatusValidate::validate($response['status']);
        return true;
    }
}
