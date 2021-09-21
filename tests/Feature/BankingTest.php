<?php

namespace Larapress\ECommerce\Tests\Feature;

use Larapress\CRUD\Tests\CustomerTestApplication;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\BankGateway;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Services\Banking\Ports\BankPortInterfaceMock;
use Larapress\ECommerce\Services\Wallet\IWalletService;

class BankingTest extends CustomerTestApplication
{
    /** @var IECommerceUser|\Illuminate\Contracts\Auth\Authenticatable */
    protected $customer;

    /**
     * Setup User Registration requirements
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = $this->createVerifiedCustomer('sample-tester', 'sample-tester', '091211111111');

        $this->zarrinpalGateway = BankGateway::factory()->makeZarrinpalGateway()->create();
        config(['larapress.ecommerce.banking.ports.zarinpal' => BankPortInterfaceMock::class]);
    }

    public function testBankingIncreaseBalanceSuccess()
    {
        $this->be($this->customer);

        $increaseAmount = 10000;
        $this->get(
            config('larapress.ecommerce.routes.bank_gateways.name') .
                '/' . config('larapress.ecommerce.banking.default_gateway') .
                '/redirect/increase/' . $increaseAmount . '/currency/' . config('larapress.ecommerce.banking.currency.id')
        )
            ->assertStatus(302)
            ->assertRedirect(BankPortInterfaceMock::BankRedirectURL);

        /** @var IWalletService */
        $walletService = app(IWalletService::class);
        $userBalance = $walletService->getUserBalance($this->customer, config('larapress.ecommerce.banking.currency.id'));
        $this->assertTrue($userBalance - 0.0 === 0.0);

        $tr = BankGatewayTransaction::query()->where('customer_id', $this->customer->id)
            ->where('status', BankGatewayTransaction::STATUS_FORWARDED)
            ->first();

        $this->get(
            config('larapress.ecommerce.routes.bank_gateways.name') . '/callback/' . $tr->id
        )
            ->assertStatus(302)
            ->assertRedirect(config('larapress.ecommerce.banking.redirect.success'));

        $userBalance = $walletService->getUserBalance($this->customer, config('larapress.ecommerce.banking.currency.id'));
        $this->assertTrue($userBalance - $increaseAmount === 0.0);
    }
}
