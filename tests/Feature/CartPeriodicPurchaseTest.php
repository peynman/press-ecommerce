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
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Cart\Base\CartInstallmentPurchaseDetails;
use Carbon\Carbon;

class CartPeriodicPurchaseTest extends CustomerTestApplication
{
    /** @var IECommerceUser|\Illuminate\Contracts\Auth\Authenticatable */
    protected $customer;

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
        for ($i = 1; $i <= 3; $i++) {
            $p = Product::factory();
            $p = $p->randomPrice()->randomPeriodicPrice();
            $p = $p->create();
            $p->types()->attach($type);
            $this->pricedItems[] = $p;
        }

        $this->customer = $this->createVerifiedCustomer('sample-tester', 'sample-tester', '091211111111');

        $this->zarrinpalGateway = BankGateway::factory()->makeZarrinpalGateway()->create();
        config(['larapress.ecommerce.banking.ports.zarinpal' => BankPortInterfaceMock::class]);
    }

    public function testCustomizedPeriodicProductPurchaseReakMoney()
    {
        $this->be($this->customer);

        /** @var ICartService */
        $cartService = app(ICartService::class);

        /** @var IWalletService */
        $bankingService = app(IWalletService::class);

        // add balance for user
        $increaseBalance = 1000000.0;
        $bankingService->addBalanceForUser(
            $this->customer,
            $increaseBalance,
            config('larapress.ecommerce.banking.currency.id'),
            WalletTransaction::TYPE_REAL_MONEY,
            WalletTransaction::FLAGS_BALANCE_PURCHASED,
            'Testing tester',
            []
        );

        // check user balance
        $userBalance = $bankingService->getUserBalance($this->customer, config('larapress.ecommerce.banking.currency.id'));
        $this->assertTrue($userBalance - $increaseBalance === 0.0);
    }

    public function testSystemPeriodicProductPurchaseSingleCartRealMoney()
    {
        $this->be($this->customer);

        /** @var ICartService */
        $cartService = app(ICartService::class);

        /** @var IWalletService */
        $bankingService = app(IWalletService::class);

        // add balance for user
        $increaseBalance = 10000000.0;
        $bankingService->addBalanceForUser(
            $this->customer,
            $increaseBalance,
            config('larapress.ecommerce.banking.currency.id'),
            WalletTransaction::TYPE_REAL_MONEY,
            WalletTransaction::FLAGS_BALANCE_PURCHASED,
            'Testing tester',
            []
        );

        // check user balance
        $userBalance = $bankingService->getUserBalance($this->customer, config('larapress.ecommerce.banking.currency.id'));
        $this->assertTrue($userBalance - $increaseBalance === 0.0);

        // add products to cart
        $total = 0.0;
        $totalPeriodic = 0.0;
        $prodIds = [];
        foreach ($this->pricedItems as $item) {
            $totalPeriodic += $item->pricePeriodic(config('larapress.ecommerce.banking.currency.id'));
            $total += $item->price(config('larapress.ecommerce.banking.currency.id'));
            $prodIds[] = $item->id;
            $cart = $this->postJson('/api/me/current-cart/add', [
                'product_id' => $item->id,
                'currency' => config('larapress.ecommerce.banking.currency.id'),
            ])
                ->assertStatus(200)
                ->assertJsonStructure([
                    'id',
                    'amount',
                    'products',
                ]);
        }

        // check cart amount as total products price
        $this->assertTrue($cart['amount'] - $total === 0.0);

        // update cart and set all products as periodic
        $cart = $this->postJson('api/me/current-cart/update', [
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'use_balance' => true,
            'periods' => $prodIds,
        ])
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    'use_balance' => true,
                    'periodic_product_ids' => $prodIds,
                ],
            ]);

        // assert new cart amount as total products periodic price
        $this->assertTrue($cart['amount'] - $totalPeriodic === 0.0);

        // redirect to bank and purchase cart
        $this->get('/bank-gateways/' . $this->zarrinpalGateway->id . '/redirect/' . $cart['id'])
            ->assertStatus(302)
            ->assertRedirect(config('larapress.ecommerce.banking.redirect.success'));

        // check user balance
        $userBalance = $bankingService->getUserBalance($this->customer, config('larapress.ecommerce.banking.currency.id'));
        $this->assertTrue($userBalance === $increaseBalance - $totalPeriodic);

        // check products access
        foreach ($this->pricedItems as $item) {
            $this->assertTrue($cartService->isProductOnPurchasedList($this->customer, $item->id));
            $this->assertFalse($cartService->isProductOnLockedList($this->customer, $item->id));
        }

        $indexer = 1;
        do {
            // get user installments
            /** @var Cart[] */
            $periods = json_decode(
                $this->getJson('api/me/installments')
                    ->assertStatus(200)
                    ->baseResponse
                    ->getContent(),
                true
            );
            // find last/first due date, check products access and lock
            /** @var Carbon */
            $lastDueDate = Carbon::now();
            /** @var Carbon */
            $firstDueDate = Carbon::now();
            // assert each installment amount
            foreach ($periods as $periodCart) {
                /** @var Product */
                $product = $periodCart['products'][0];

                $purchaseDetails = new CartInstallmentPurchaseDetails($product['pivot']['data']);

                foreach ($this->pricedItems as $item) {
                    if ($item->id === $product['id']) {
                        // check installment cart amount for product
                        $this->assertTrue($periodCart['amount'] - $item->getPeriodicPurchaseAmount() === 0.0);
                        $this->assertTrue($purchaseDetails->index === $indexer);
                    }
                }

                if (is_null($lastDueDate) || $lastDueDate->isBefore($purchaseDetails->due_date)) {
                    $lastDueDate = $purchaseDetails->due_date;
                }
                if (is_null($firstDueDate) || $firstDueDate->isAfter($purchaseDetails->due_date)) {
                    $firstDueDate = $purchaseDetails->due_date;
                }
            }

            // travel to first due date
            $this->travel(Carbon::now()->diffInDays($firstDueDate) + 1)->days();
            $now = Carbon::now();
            $cartService->resetPurchasedCache($this->customer->id);
            // check products access on first due date
            foreach ($periods as $periodCart) {
                /** @var Product */
                $product = $periodCart['products'][0];

                $purchaseDetails = new CartInstallmentPurchaseDetails($product['pivot']['data']);

                if ($purchaseDetails->due_date->isAfter($now)) {
                    $this->assertFalse($cartService->isProductOnLockedList($this->customer, $product['id']));
                } else {
                    $this->assertTrue($cartService->isProductOnLockedList($this->customer, $product['id']));
                }
            }

            // travel to last due date
            $this->travel(Carbon::now()->diffInDays($lastDueDate) + 1)->days();
            $now = Carbon::now();
            $cartService->resetPurchasedCache($this->customer->id);
            // check products access on last due date
            foreach ($periods as $periodCart) {
                /** @var Product */
                $product = $periodCart['products'][0];

                $purchaseDetails = new CartInstallmentPurchaseDetails($product['pivot']['data']);

                $this->assertTrue($cartService->isProductOnLockedList($this->customer, $product['id']));
            }

            // pay installments for all products
            foreach ($periods as $periodCart) {
                $this->post('/api/me/installments/' . $periodCart['id'], [
                    'use_balance' => true,
                ])
                    ->assertStatus(200)
                    ->assertJson([
                        'id' => $periodCart['id'],
                        'amount' => $periodCart['amount'],
                    ]);

                $this->get('/bank-gateways/' . $this->zarrinpalGateway->id . '/redirect/' . $periodCart['id'])
                    ->assertStatus(302)
                    ->assertRedirect(config('larapress.ecommerce.banking.redirect.success'));
            }

            $cartService->resetPurchasedCache($this->customer->id);
            // get user installments again
            /** @var Cart[] */
            $periods = json_decode(
                $this->getJson('api/me/installments')
                    ->assertStatus(200)
                    ->baseResponse
                    ->getContent(),
                true
            );

            // check product access again,
            foreach ($periods as $periodCart) {
                /** @var Product */
                $product = $periodCart['products'][0];

                $purchaseDetails = new CartInstallmentPurchaseDetails($product['pivot']['data']);


                if ($purchaseDetails->due_date->isAfter($now)) {
                    $this->assertFalse($cartService->isProductOnLockedList($this->customer, $product['id']));
                } else {
                    $this->assertTrue($cartService->isProductOnLockedList($this->customer, $product['id']));
                }
            }

            $indexer++;
        } while (count($periods) > 0);

        $cartService->resetPurchasedCache($this->customer->id);
        // check product access again,
        foreach ($this->pricedItems as $item) {
            $this->assertFalse($cartService->isProductOnLockedList($this->customer, $item->id));
        }
    }
}
