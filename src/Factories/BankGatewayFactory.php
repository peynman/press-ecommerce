<?php

namespace Larapress\ECommerce\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Larapress\ECommerce\Models\BankGateway;

class BankGatewayFactory extends Factory
{
    protected $model = BankGateway::class;

    public function definition()
    {
        return [
            'author_id' => 1,
            'type' => 'zarinpal',
            'flags' => 0,
            'data' => [
                'merchantId' => $this->faker->randomNumber(6),
                'email' => $this->faker->email,
                'mobile' => $this->faker->phoneNumber,
                'isSandbox' => true,
                'isZarinGate' => false,
            ]
        ];
    }

    public function makeZarrinpalGateway()
    {
        return $this->state(function ($attrs) {
            return [
                'type' => 'zarinpal',
                'data' => array_merge($attrs['data'], [
                    'merchantId' => $this->faker->randomNumber(6),
                    'email' => $this->faker->email,
                    'mobile' => $this->faker->phoneNumber,
                    'isSandbox' => true,
                    'isZarinGate' => false,
                ])
            ];
        });
    }


    public function diabled()
    {
        return $this->state(function ($attrs) {
            return [
                'flags' => BankGateway::FLAGS_DISABLED,
            ];
        });
    }
}
