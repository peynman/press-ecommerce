<?php

namespace Larapress\ECommerce\Repositories;

interface IBankGatewayRepository
{

    /**
     * @param IProfileUser|ICRUDUser $user
     * @return array
     */
    public function getAllBankGatewayTypes($user);
}
