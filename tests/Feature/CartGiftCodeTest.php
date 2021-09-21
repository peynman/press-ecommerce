<?php

namespace Larapress\ECommerce\Tests\Feature;

use Larapress\CRUD\Tests\CustomerTestApplication;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\ECommerce\Models\ProductType;

class CartGiftCodeTest extends CustomerTestApplication
{
    /** @var IECommerceUser|\Illuminate\Contracts\Auth\Authenticatable */
    protected $customer;

    /** @var GiftCode */
    protected $giftCode;
    /** @var GiftCode */
    protected $fixedCode;

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
        $this->fixedCode = GiftCode::factory()->limitProducts([1, 3])->limitFixedProductsOnly(true)->create();
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
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) +
                    $p2->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) +
                    $p3->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) +
                    $p4->pricePeriodic(config('larapress.ecommerce.banking.currency.id')),
            ]);

        // check gift code on products 2,3
        $percent = floatval($this->giftCode->data['value']) / 100.0;
        $this->postJson('api/me/current-cart/apply/gift-code', [
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'gift_code' => $this->giftCode->code,
            // this is not an update for cart, it just checks if gift_code is valid, so we dont need to include 'periods'
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' =>
                floor($p2->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) * $percent) +
                    floor($p3->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) * $percent),
                'products' => [
                    '2' => floor($p2->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) * $percent),
                    '3' => floor($p3->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) * $percent),
                ],
                'code_id' => 1,
                'percent' => $percent,
            ]);


        $offP2 = floor($p2->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) * $percent);
        $offP3 = floor($p3->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) * $percent);
        // update cart with gift code and periodic products
        $this->postJson('api/me/current-cart/update', [
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'gift_code' => $this->giftCode->code,
            'periods' => [
                2, 3, 4
            ],
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) +
                    $p4->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) +
                    $p2->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) +
                    $p3->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) - ($offP2 + $offP3),

                'data' => [
                    'gift_code' => [
                        'products' => [
                            2 => $offP2,
                            3 => $offP3,
                        ],
                        'amount' => $offP2 + $offP3,
                    ]
                ]
            ]);

        // check gift code on products 1 as fixed and 2,3,4 as periodic
        $fixedPercent = floatval($this->fixedCode->data['value']) / 100.0;
        $offP1 = floor($p1->price(config('larapress.ecommerce.banking.currency.id')) * $fixedPercent);
        // update cart with fixed gift code and periodic products
        $this->postJson('api/me/current-cart/update', [
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'gift_code' => $this->fixedCode->code,
            'periods' => [
                2, 3, 4
            ],
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) +
                    $p4->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) +
                    $p2->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) +
                    $p3->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) - $offP1,
                'data' => [
                    'gift_code' => [
                        'products' => [
                            1 => $offP1,
                        ],
                        'amount' => $offP1,
                    ]
                ]
            ]);
    }
}
