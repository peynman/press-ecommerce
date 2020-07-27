<?php

namespace Larapress\ECommerce\Services\AdobeConnect;

interface IAdobeConnectService {

    /**
     * Undocumented function
     *
     * @param [type] $options
     * @return void
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
     * @param [type] $folderName
     * @param [type] $meetingName
     * @return void
     */
    public function createMeeting($folderName, $meetingName);

    /**
     * Undocumented function
     *
     * @param [type] $folderName
     * @param [type] $meetingName
     * @param [type] $username
     * @return void
     */
    public function addParticipantToMeeting($folderName, $meetingName, $username);

    /**
     * Undocumented function
     *
     * @param [type] $meetingName
     * @param [type] $username
     * @param [type] $password
     * @return void
     */
    public function redirectToMeeting($meetingName, $username, $password);


    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param int $product_id
     * @return Response
     */
    public function verifyProductMeeting($user, $product_id);



    /**
     * Undocumented function
     *
     * @param Product $item
     * @return void
     */
    public function createMeetingForProduct($item);
}
