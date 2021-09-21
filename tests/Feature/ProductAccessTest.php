<?php

namespace Larapress\ECommerce\Tests\Feature;

use Larapress\CRUD\Tests\CustomerTestApplication;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\BankGateway;
use Larapress\ECommerce\Models\ProductType;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Cart\ICartService;
use Larapress\ECommerce\Services\Wallet\IWalletService;
use Larapress\ECommerce\Services\Banking\Ports\BankPortInterfaceMock;

class ProductAccessTest extends CustomerTestApplication
{
    /** @var IECommerceUser|\Illuminate\Contracts\Auth\Authenticatable */
    protected $customer;

    protected $nullForAllIds = [];
    protected $zeroPriceIds = [];
    protected $pricedIds = [];
    protected $freeSubItems = [];
    protected $nullSubItems = [];
    protected $zeroPricedSubItems = [];
    protected $pricedSubItems = [];
    /** @var Product[] */
    protected $pricedItems = [];

    /** @var BankGateway */
    protected $zarrinpalGateway;

    /**
     * Setup User Registration requirements
     */
    protected function setUp(): void
    {
        parent::setUp();

        $type = ProductType::factory()->create();
        $products = [];
        for ($i = 1; $i <= 10; $i++) {
            $p = Product::factory();
            if ($i % 3 != 0 && $i % 5 != 0) {
                $p = $p->randomPrice();
            } else if ($i % 5 == 0) {
                $p = $p->addPrice(0, config('larapress.ecommerce.banking.currency.id'));
            } else if ($i == 3) {
                $p = $p->emptyPriceList();
            }
            $p = $p->create();
            $p->types()->attach($type);

            if ($i % 3 != 0 && $i % 5 != 0) {
                $this->pricedIds[] = $p->id;
                $this->pricedItems[] = $p;

                if ($i % 2 == 0) {
                    for ($j = 1; $j < 10; $j++) {
                        $sp = Product::factory()->childOf($p->id);
                        if ($j % 3 != 0 && $j % 5 != 0) {
                            $sp = $sp->randomPrice();
                        } else if ($j % 5 == 0) {
                            $sp = $sp->addPrice(0, config('larapress.ecommerce.banking.currency.id'));
                        } else if ($j == 3) {
                            $sp = $sp->freeAccess(true);
                        } else {
                            // null item
                        }

                        $sp = $sp->create();

                        if ($j % 3 != 0 && $j % 5 != 0) {
                            $this->pricedSubItems[] = $sp->id;
                        } else if ($j % 5 == 0) {
                            $this->zeroPricedSubItems[] = $sp->id;
                        } else if ($j == 3) {
                            $this->freeSubItems[] = $sp->id;
                        } else {
                            $this->nullSubItems[] = $sp->id;
                        }
                    }
                }
            } else if ($i % 5 == 0) {
                $this->zeroPriceIds[] = $p->id;
            } else {
                $this->nullForAllIds[] = $p->id;
            }

            $products[] = $p;
        }

        $this->customer = $this->createVerifiedCustomer('sample-tester', 'sample-tester', '091211111111');

        $this->zarrinpalGateway = BankGateway::factory()->makeZarrinpalGateway()->create();
        config(['larapress.ecommerce.banking.ports.zarinpal' => BankPortInterfaceMock::class]);
    }

    public function testGlobalAccess()
    {
        $this->be($this->customer);

        /** @var ICartService */
        $cartService = app(ICartService::class);

        //has access
        foreach ($this->nullForAllIds as $id) {
            $this->assertTrue($cartService->isProductOnPurchasedList($this->customer, $id));
        }
        foreach ($this->freeSubItems as $id) {
            $this->assertTrue($cartService->isProductOnPurchasedList($this->customer, $id));
        }

        //does not have access
        foreach ($this->zeroPriceIds as $id) {
            $this->assertFalse($cartService->isProductOnPurchasedList($this->customer, $id));
        }
        foreach ($this->pricedIds as $id) {
            $this->assertFalse($cartService->isProductOnPurchasedList($this->customer, $id));
        }
        foreach ($this->pricedSubItems as $id) {
            $this->assertFalse($cartService->isProductOnPurchasedList($this->customer, $id));
        }
        foreach ($this->nullSubItems as $id) { // null sub items need means it has to be purchased by parent
            $this->assertFalse($cartService->isProductOnPurchasedList($this->customer, $id));
        }
        foreach ($this->zeroPricedSubItems as $id) { // zero sub items dont need to be purchased
            $this->assertFalse($cartService->isProductOnPurchasedList($this->customer, $id));
        }
    }

    public function testFreeProductPurchaseIndividual()
    {
        $this->be($this->customer);

        /** @var ICartService */
        $cartService = app(ICartService::class);

        foreach ($this->zeroPriceIds as $id) {
            $cart = $this->postJson('/api/me/current-cart/add', [
                'product_id' => $id,
                'currency' => config('larapress.ecommerce.banking.currency.id'),
            ])
                ->assertStatus(200)
                ->assertJsonStructure([
                    'id'
                ]);

            $this->get('/bank-gateways/' . $this->zarrinpalGateway->id . '/redirect/' . $cart['id'])
                ->assertStatus(302)
                ->assertRedirect(config('larapress.ecommerce.banking.redirect.success'));
        }

        foreach ($this->zeroPriceIds as $id) {
            $this->assertTrue($cartService->isProductOnPurchasedList($this->customer, $id));
        }
    }

    public function testFreeProductPurchaseSingleCart()
    {
        $this->be($this->customer);

        /** @var ICartService */
        $cartService = app(ICartService::class);

        foreach ($this->zeroPriceIds as $id) {
            $cart = $this->postJson('/api/me/current-cart/add', [
                'product_id' => $id,
                'currency' => config('larapress.ecommerce.banking.currency.id'),
            ])
                ->assertStatus(200)
                ->assertJsonStructure([
                    'id',
                ]);
        }

        $this->get('/bank-gateways/' . $this->zarrinpalGateway->id . '/redirect/' . $cart['id'])
            ->assertStatus(302)
            ->assertRedirect(config('larapress.ecommerce.banking.redirect.success'));

        foreach ($this->zeroPriceIds as $id) {
            $this->assertTrue($cartService->isProductOnPurchasedList($this->customer, $id));
        }
    }

    public function testPricedProductPurchaseSingleCartVirtualMoney()
    {
        $this->be($this->customer);

        /** @var ICartService */
        $cartService = app(ICartService::class);

        /** @var IWalletService */
        $bankingService = app(IWalletService::class);

        $increaseBalance = 1000000.0;
        $bankingService->addBalanceForUser(
            $this->customer,
            $increaseBalance,
            config('larapress.ecommerce.banking.currency.id'),
            WalletTransaction::TYPE_VIRTUAL_MONEY,
            WalletTransaction::FLAGS_BALANCE_PURCHASED,
            'Testing tester',
            []
        );

        $userBalance = $bankingService->getUserBalance($this->customer, config('larapress.ecommerce.banking.currency.id'));
        $this->assertTrue($userBalance - $increaseBalance === 0.0);

        $total = 0.0;
        foreach ($this->pricedItems as $item) {
            $total += $item->price(config('larapress.ecommerce.banking.currency.id'));
            $cart = $this->postJson('/api/me/current-cart/add', [
                'product_id' => $item->id,
                'currency' => config('larapress.ecommerce.banking.currency.id'),
            ])
                ->assertStatus(200)
                ->assertJsonStructure([
                    'id',
                ]);
        }

        $this->assertTrue($cart['amount'] - $total === 0.0);

        $this->postJson('api/me/current-cart/update', [
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'use_balance' => true,
        ])
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'use_balance' => true,
                ]
            ]);

        $this->get('/bank-gateways/' . $this->zarrinpalGateway->id . '/redirect/' . $cart['id'])
            ->assertStatus(302)
            ->assertRedirect(config('larapress.ecommerce.banking.redirect.success'));

        $userBalance = $bankingService->getUserBalance($this->customer, config('larapress.ecommerce.banking.currency.id'));
        $this->assertTrue($userBalance === $increaseBalance - $total);

        foreach ($this->pricedItems as $item) {
            $this->assertTrue($cartService->isProductOnPurchasedList($this->customer, $item->id));
        }
    }
}
