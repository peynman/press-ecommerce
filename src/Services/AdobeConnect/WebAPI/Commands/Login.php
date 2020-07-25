<?php

namespace Larapress\ECommerce\Services\AdobeConnect\WebAPI\Commands;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Command;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Converter\Converter;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Exceptions\NoDataException;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\StatusValidate;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Helpers\HeaderParse;

/**
 * Call the Login action and save the session cookie.
 *
 * More info see {@link https://helpx.adobe.com/content/help/en/adobe-connect/webservices/login.html}
 */
class Login extends Command
{
    /**
     * @var array
     */
    protected $parameters;

    /**
     * @param string $login
     * @param string $password
     */
    public function __construct($login, $password)
    {
        $this->parameters = [
            'action' => 'login',
            'login' => (string) $login,
            'password' => (string) $password
        ];
    }

    /**
     * @inheritdoc
     *
     * @return bool
     */
    protected function process()
    {
        $response = $this->client->doGet($this->parameters);
        $responseConverted = Converter::convert($response);

        try {
            StatusValidate::validate($responseConverted['status']);
        } catch (NoDataException $e) { // Invalid Login
            $this->client->setSession('');
            return false;
        }
        $cookieHeader = HeaderParse::parse($response->getHeader('Set-Cookie'));
        $this->client->setSession($cookieHeader[0]['BREEZESESSION']);
        return true;
    }
}
