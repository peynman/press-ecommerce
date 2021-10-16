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
        /** @var BankGateway[] */
        $gateways = BankGateway::query()->whereRaw('(flags & ' . BankGateway::FLAGS_DISABLED . ') = 0')->get(['id', 'name', 'type', 'data']);

        foreach ($gateways as $gateway) {
            $gateway->setAttribute('title', $gateway->data['title'] ?? $gateway->name);
            $gateway->setHidden(['data']);
        }

        return $gateways;
    }
}
