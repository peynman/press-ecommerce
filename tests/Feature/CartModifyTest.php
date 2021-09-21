<?php

namespace Larapress\ECommerce\Tests\Feature;

use Larapress\CRUD\Tests\CustomerTestApplication;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\ProductType;

class CartModifyTest extends CustomerTestApplication
{
    /** @var IECommerceUser|\Illuminate\Contracts\Auth\Authenticatable */
    protected $customer;
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
    }

    public function testCartModify()
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
        // add single item to cart
        $this->postJson('/api/me/current-cart/add', [
            'product_id' => 1,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
        ])
            ->assertStatus(200)
            ->assertJsonStructure([
                'id',
                'amount',
                'currency',
                'customer_id',
                'products' => [
                    '*' => [
                        'id',
                        'pivot' => [
                            'data' => [
                                'amount',
                                'quantity',
                                'currency',
                            ]
                        ]

                    ]
                ]
            ])
            ->assertJsonCount(1, 'products')
            ->assertJsonMissing([
                'products' => [
                    '*' => [
                        'author'
                    ]
                ],
            ])
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')),
            ]);

        // add same product again
        $this->postJson('api/me/current-cart/add', [
            'product_id' => 1,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'quantity' => 5,
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) * 6,
            ]);


        // remove same product again 3 times
        $this->postJson('api/me/current-cart/remove', [
            'product_id' => 1,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'quantity' => 3,
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) * 3,
            ]);

        // remove product 2 again 3 times
        $this->postJson('api/me/current-cart/remove', [
            'product_id' => 2,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'quantity' => 3,
        ])

            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) * 3,
            ]);

        // add product 2 again 3 times
        $this->postJson('api/me/current-cart/add', [
            'product_id' => 2,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'quantity' => 3,
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) * 3 +
                    $p2->price(config('larapress.ecommerce.banking.currency.id')) * 3,
            ]);

        // remove product 2 again 2 times
        $this->postJson('api/me/current-cart/remove', [
            'product_id' => 2,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'quantity' => 2,
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) * 3 +
                    $p2->price(config('larapress.ecommerce.banking.currency.id')),
            ]);


        // make product 2 detach
        $this->postJson('api/me/current-cart/remove', [
            'product_id' => 2,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'quantity' => 1,
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) * 3,
            ])
            ->assertJsonCount(1, 'products');


        // add product 2 again 1 time
        $this->postJson('api/me/current-cart/add', [
            'product_id' => 2,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'quantity' => 1,
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) * 3 +
                    $p2->price(config('larapress.ecommerce.banking.currency.id')),
            ])
            ->assertJsonCount(2, 'products');

        // add product 3
        $this->postJson('api/me/current-cart/add', [
            'product_id' => 3,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'quantity' => 1,
        ])
            ->assertStatus(200)
            ->assertJson([
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) * 3 +
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
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) * 3 +
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
                'amount' => $p1->price(config('larapress.ecommerce.banking.currency.id')) * 3 +
                    $p2->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) +
                    $p3->pricePeriodic(config('larapress.ecommerce.banking.currency.id')) +
                    $p4->pricePeriodic(config('larapress.ecommerce.banking.currency.id')),
            ]);
    }
}
