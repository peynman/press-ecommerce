<?php

namespace Larapress\ECommerce\Services\Banking;

interface IBankGatewayRepository
{
    /**
     * @param IProfileUser|ICRUDUser $user
     * @return array
     */
    public function getAllBankGateways($user);
}
