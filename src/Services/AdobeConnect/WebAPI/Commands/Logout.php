<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Commands;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Command;

/**
 * Ends the session
 *
 * More info see {@link https://helpx.adobe.com/content/help/en/adobe-connect/webservices/logout.html}
 */
class Logout extends Command
{
    /**
     * @inheritdoc
     *
     * @return bool
     */
    protected function process()
    {
        $this->client->doGet([
            'action' => 'logout',
            'session' => $this->client->getSession()
        ]);
        $this->client->setSession('');
        return true;
    }
}
