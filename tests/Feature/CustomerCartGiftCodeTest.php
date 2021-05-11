<?php

namespace Larapress\ECommerce\Tests\Feature;

use Larapress\CRUD\Tests\CustomerTestApplication;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\ECommerce\Models\ProductType;

class CustomerCartGiftCodeTest extends CustomerTestApplication
{
    /** @var IECommerceUser|\Illuminate\Contracts\Auth\Authenticatable */
    protected $customer;

    /** @var GiftCode */
    protected $giftCode;
    /**
     * Setup User Registration requirements
     */
    protected function setUp(): void
    {
        parent::setUp();

        $type = ProductType::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $p = Product::factory()
                ->randomPrice()
                ->randomPeriodicPrice()
                ->quantized()
                ->create();
            $p->types()->attach($type);
        }

        $this->customer = $this->createVerifiedCustomer('sample-tester', 'sample-tester', '091211111111');

        $this->giftCode = GiftCode::factory()->limitProducts([2, 3])->create();
    }

    public function testCartGiftCodeModify()
    {
        /** @var Product */
        $p1 = Product::find(1);
        /** @var Product */
        $p2 = Product::find(2);
        /** @var Product */
        $p3 = Product::find(3);
        /** @var Product */
        $p4 = Product::find(4);

        $this->be($this->customer);
        // add product 1
        $this->postJson('api/me/current-cart/add', [
            'product_id' => 1,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')),
            ]);

        // add product 2 again 3 times
        $this->postJson('api/me/current-cart/add', [
            'product_id' => 2,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'quantity' => 1,
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) +
                    $p2->price(config('larapress.ecommerce.banking.currency.id')),
            ]);

        // add product 3
        $this->postJson('api/me/current-cart/add', [
            'product_id' => 3,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'quantity' => 1,
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) +
                    $p2->price(config('larapress.ecommerce.banking.currency.id')) +
                    $p3->price(config('larapress.ecommerce.banking.currency.id')),
            ])
            ->assertJsonCount(3, 'products');

        // add product 4
        $this->postJson('api/me/current-cart/add', [
            'product_id' => 4,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'quantity' => 1,
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) +
                    $p2->price(config('larapress.ecommerce.banking.currency.id')) +
                    $p3->price(config('larapress.ecommerce.banking.currency.id')) +
                    $p4->price(config('larapress.ecommerce.banking.currency.id')),
            ])
            ->assertJsonCount(4, 'products');

        // make product 2,3,4 periodic
        $this->postJson('api/me/current-cart/update', [
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'periods' => [
                2, 3, 4
            ],
        ])
            ->assertStatus(200)
            ->assertJson([
                'cart' => [
                    'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) +
                        $p2->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) +
                        $p3->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) +
                        $p4->pricePeriodic(config('larapress.ecommerce.banking.currency.id')),
                ]
            ]);

        $this->postJson('api/me/current-cart/gift-code/apply', [
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'gift_code' => $this->giftCode->code,
        ])->dump();
    }
}
