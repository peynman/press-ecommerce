<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Commands;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Command;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Entities\Permission;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Converter\Converter;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StatusValidate;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\SetEntityAttributes as FillObject;

/**
 * Get the Principal's permission in a SCO, Principal or Account
 *
 * More info see {@link https://helpx.adobe.com/adobe-connect/webservices/permissions-info.html}
 */
class PermissionInfoFromPrincipal extends Command
{
    /**
     * @var int
     */
    protected $aclId;

    /**
     * @var int
     */
    protected $principalId;

    /**
     * @param int $aclId
     * @param int $principalId
     */
    public function __construct($aclId, $principalId)
    {
        $this->aclId = $aclId;
        $this->principalId = $principalId;
    }

    /**
     * @inheritdoc
     *
     * @return Permission
     */
    protected function process()
    {
        $response = Converter::convert(
            $this->client->doGet([
                'action' => 'permissions-info',
                'acl-id' => $this->aclId,
                'principal-id' => $this->principalId,
                'session' => $this->client->getSession()
            ])
        );
        StatusValidate::validate($response['status']);
        $permission = new Permission();
        FillObject::setAttributes($permission, $response['permission']);
        return $permission;
    }
}
