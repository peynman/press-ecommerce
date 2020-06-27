<?php

namespace Larapress\ECommerce\Repositories;

use Illuminate\Support\Facades\DB;
use Larapress\ECommerce\Models\BankGateway;

class BankGatewayRepository implements IBankGatewayRepository {
    /**
     * @param IProfileUser|ICRUDUser $user
     * @return array
     */
    public function getAllBankGatewayTypes($user) {
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
    public function getAllBankGateways($user) {
        $gateways = BankGateway::all(['id', 'type']);

        return $gateways;
    }
}
