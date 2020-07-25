<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Commands;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Command;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\ArrayableInterface;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Converter\Converter;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Filter;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StatusValidate;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\SetEntityAttributes as FillObject;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Entities\Principal;

/**
 * Provides a complete list of users and groups, including primary groups.
 *
 * More info see {@link https://helpx.adobe.com/adobe-connect/webservices/principal-list.html}
 */
class PrincipalList extends Command
{
    /**
     * @var array
     */
    protected $parameters;

    /**
     * @param int $groupId The Principal ID of a group. If indicate will be possible filter by isMember.
     * @param ArrayableInterface|null $filter
     * @param ArrayableInterface|null $sorter
     */
    public function __construct(
        $groupId = 0,
        ArrayableInterface $filter = null,
        ArrayableInterface $sorter = null
    ) {
        $this->parameters = [
            'action' => 'principal-list',
        ];

        if ($groupId) {
            $this->parameters['group-id'] = $groupId;
        }

        if ($filter) {
            $this->parameters += $filter->toArray();
        }

        if ($sorter) {
            $this->parameters += $sorter->toArray();
        }
    }

    /**
     * @inheritdoc
     *
     * @return Principal[]
     */
    protected function process()
    {
        $response = Converter::convert(
            $this->client->doGet(
                $this->parameters + ['session' => $this->client->getSession()]
            )
        );

        StatusValidate::validate($response['status']);

        $principals = [];

        foreach ($response['principalList'] as $principalAttributes) {
            $principal = new Principal();
            FillObject::setAttributes($principal, $principalAttributes);
            $principals[] = $principal;
        }

        return $principals;
    }
}
