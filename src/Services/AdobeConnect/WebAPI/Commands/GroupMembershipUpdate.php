<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Commands;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Command;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Converter\Converter;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StatusValidate;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\ValueTransform as VT;

/**
 * Adds one principal to a group, or removes one principal from a group.
 *
 * More info see {@link https://helpx.adobe.com/adobe-connect/webservices/group-membership-update.html}
 */
class GroupMembershipUpdate extends Command
{
    /**
     * @var array
     */
    protected $parameters;

    /**
     * @param int $groupId
     * @param int $principalId
     * @param bool $isMember
     */
    public function __construct($groupId, $principalId, $isMember)
    {
        $this->parameters = [
            'action' => 'group-membership-update',
            'group-id' => $groupId,
            'principal-id' => $principalId,
            'is-member' => VT::toString($isMember),
        ];
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
