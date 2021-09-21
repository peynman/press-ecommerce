<?php

namespace Larapress\ECommerce\Services\Banking;

use Larapress\ECommerce\Models\BankGateway;

class BankGatewayRepository implements IBankGatewayRepository
{
    /**
     * Undocumented function
     *
     * @param [type] $user
     * @return void
     */
    public function getAllBankGateways($user)
    {
        return BankGateway::query()
            ->select(['id', 'name', 'type'])
            ->whereRaw('(flags & ' . BankGateway::FLAGS_DISABLED . ') = 0')
            ->get(['id', 'name', 'type']);
    }
}
