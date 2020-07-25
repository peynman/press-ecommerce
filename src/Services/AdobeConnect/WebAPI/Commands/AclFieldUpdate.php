<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Commands;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Command;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\ArrayableInterface;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Converter\Converter;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StatusValidate;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StringCaseTransform as SCT;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\ValueTransform as VT;

/**
 * Updates the passed in field-id for the specified SCO, Principal or Account.
 *
 * More info see {@link https://helpx.adobe.com/adobe-connect/webservices/acl-field-update.html}
 */
class AclFieldUpdate extends Command
{
    /**
     * @var array
     */
    protected $parameters;

    /**
     *
     * @param int $aclId
     * @param string $fieldId
     * @param mixed $value
     * @param ArrayableInterface|null $extraParams
     */
    public function __construct($aclId, $fieldId, $value, ArrayableInterface $extraParams = null)
    {
        $this->parameters = [
            'action' => 'acl-field-update',
            'acl-id' => $aclId,
            'field-id' => SCT::toHyphen($fieldId),
            'value' => VT::toString($value),
        ];

        if ($extraParams) {
            $this->parameters += $extraParams->toArray();
        }
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
