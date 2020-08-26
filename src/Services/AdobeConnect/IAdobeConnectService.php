<?php

namespace Larapress\ECommerce\Services\AdobeConnect;

use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Entities\SCO;
use Larapress\ECommerce\Services\AdobeConnect\WebAPI\Entities\Principal;
use Larapress\Profiles\IProfileUser;

interface IAdobeConnectService {
    /**
     * Undocumented function
     *
     * @param string $url
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function connect($url, $username, $password);

    /**
     * Undocumented function
     *
     * @param string $username
     * @param string $password
     * @param string $displayName
     * @return bool
     */
    public function syncUserAccount($username, $password, $firstname, $lastname);

    /**
     * Undocumented function
     *
     * @param string $folderName
     * @param string $meetingName
     * @param array $details
     * @return SCO
     */
    public function createOrGetMeeting($folderName, $meetingName, $details = []);

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param int $product_id
     * @return Response
     */
    public function verifyProductMeeting($user, $product_id);

    /**
     * Undocumented function
     *
     * @param Product $item
     * @return SCO
     */
    public function createMeetingForProduct($item);

    /**
     * Undocumented function
     *
     * @param Product $item
     * @param callable(meetingFolder, meetingName) $callback
     * @return void
     */
    public function onEachServerForProduct($item, $callback);

    /**
     * @return Principal
     */
    public function getLoggedUserInfo();

    /**
     * Undocumented function
     *
     * @param string $username
     * @return Principal|null
     */
    public function getUserInfo($username);

    /**
     * Undocumented function
     *
     * @param string $meetingId
     * @return array
     */
    public function getMeetingSessions($meetingId);

    /**
     * Undocumented function
     *
     * @param string $meetingId
     * @return array
     */
    public function getMeetingAttendance($meetingId);

    /**
     * Undocumented function
     *
     * @param string $login
     * @return IProfileUser|null
     */
    public function getUserFromACLogin($login);

    /**
     * Undocumented function
     *
     * @param string $meetingId
     * @return array
     */
    public function getMeetingRecordings($meetingId);
}
