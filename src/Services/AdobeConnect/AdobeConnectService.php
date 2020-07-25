<?php

namespace Larapress\ECommerce\Services\AdobeConnect;

use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Client;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Connection\Curl\Connection;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Entities\Principal;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Entities\SCO;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Filter;

class AdobeConnectService implements IAdobeConnectService
{
    /** @var Connection */
    protected $connection = null;
    /** @var Client */
    protected $client = null;

    protected $url = null;

    /**
     * Undocumented function
     *
     * @param [type] $options
     * @return void
     */
    public function connect($url, $username, $password)
    {
        if ($this->url !== $url) {
            $this->url = $url;
            $this->connection = new Connection($url);
            $this->client =  new Client($this->connection);
            $this->client->login($username, $password);
        }
    }

    /**
     * Undocumented function
     *
     * @param string $username
     * @param string $password
     * @param string $displayName
     * @return bool
     */
    public function syncUserAccount($username, $password, $displayName)
    {
        $filter = Filter::instance()->equals('login', $username);
        $existing = $this->client->principalList(0, $filter);
        if (count($existing) === 0) {
            $principal = Principal::instance()
                ->setName($username)
                ->setPassword($password)
                ->setFirstName($displayName);
            $obj = $this->client->principalCreate($principal);
        }

        return $obj;
    }


    /**
     * Undocumented function
     *
     * @param [type] $folderName
     * @param [type] $meetingName
     * @return SCO
     */
    public function createMeeting($folderName, $meetingName)
    {
        $folderId = null;
        $ids = $this->client->scoShortcuts();
        foreach ($ids as $scoFolder) {
            if (isset($scoFolder['type']) && $scoFolder['type'] === $folderName) {
                $folderId = $scoFolder['scoId'];
            }
        }

        if (is_null($folderId)) {
            Log::critical('Adobe Connect folder with typ: ' . $folderName . ' not found');
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        $filter = Filter::instance()->equals('name', $meetingName);
        $scos = $this->client->scoContents($folderId, $filter);

        if (count($scos) === 0) {
            $sco = SCO::instance()
                ->setName($meetingName)
                ->setType(SCO::TYPE_MEETING)
                ->setFolderId($folderId);
            $this->client->scoCreate($sco);
        } else {
            $sco = $scos[0];
        }

        return $sco;
    }

    /**
     * Undocumented function
     *
     * @param [type] $folderName
     * @param [type] $meetingName
     * @param [type] $username
     * @return void
     */
    public function addParticipantToMeeting($folderName, $meetingName, $username)
    {
    }

    /**
     * Undocumented function
     *
     * @param [type] $meetingName
     * @param [type] $username
     * @param [type] $password
     * @return void
     */
    public function redirectToMeeting($meetingName, $username, $password)
    {
    }
}
