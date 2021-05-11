<?php

namespace Larapress\ECommerce\Services\Banking;

use Larapress\ECommerce\Models\BankGateway;

class BankGatewayRepository implements IBankGatewayRepository
{
    /**
     * @param IProfileUser $user
     * @return array
     */
    public function getAllBankGatewayTypes($user)
    {
        return [
            'zarinpal' => [
                'merchant_id' => [
                    'type' => 'input',
                    'input' => 'text',
                    'label' => 'Merchant ID',
                ],
                'email' => [
                    'type' => 'input',
                    'input' => 'text',
                    'label' => 'Email',
                ],
                'mobile' => [
                    'type' => 'input',
                    'input' => 'text',
                    'label' => 'Mobile',
                ],
                'isZarinGate' => [
                    'type' => 'input',
                    'input' => 'switch',
                    'label' => 'Zarin Gate',
                ],
                'isSandbox' => [
                    'type' => 'input',
                    'input' => 'switch',
                    'label' => 'Sandbox Mode',
                ],
            ],
            'mellat' => [],
            'paypal' => [],
        ];
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @return void
     */
    public function getAllBankGateways($user)
    {
        return BankGateway::query()
            ->select(['id', 'type'])
            ->whereRaw('(flags & ' . BankGateway::FLAGS_DISABLED . ') = 0')
            ->get(['id', 'type']);
    }
}
