<?php

namespace Larapress\ECommerce\Tests\Feature;

use Larapress\CRUD\Tests\BaseCRUDTestTrait;
use Larapress\CRUD\Tests\PackageTestApplication;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;

class ProductCRUDTest extends PackageTestApplication
{
    use BaseCRUDTestTrait;

    /**
     * Undocumented function
     *
     * @group product
     *
     * @return void
     */
    public function testCRUDUpdate()
    {
        $this->doCRUDCreateTest(
            new ProductCRUDProvider(),
            [
                'name' => 'sample-product',
                'data' => [
                    'title' => 'Sample Product',
                ],
            ]
        )
            ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'object' => [
                    'id',
                    'author_id',
                ]
            ]);

        $this->doCRUDUpdateTest(
            new ProductCRUDProvider(),
            1,
            [
                'name' => 'sample-product',
                'data' => [
                    'title' => 'Sample Product Updated',
                    'pricing' => [
                        [
                            'priority' => 0,
                            'amount' => 10000,
                            'currency' => 1,
                        ]
                    ],
                    'price_periodic' => [
                        [
                            'priority' => 0,
                            'amount' => 1000,
                            'currency' => 1,
                        ]
                    ],
                    'calculate_periodic' => [
                        'period_count' => 9,
                        'period_amount' => 1000,
                        'period_duration' => 30,
                    ]
                ],
            ]
        )
        ->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'object' => [
                    'id',
                    'author_id',
                ]
            ])->assertJson([
                'object' => [
                    'data' => [
                        'title' => 'Sample Product Updated',
                        'pricing' => [
                            [
                                'priority' => 0,
                                'amount' => 10000,
                                'currency' => 1,
                            ]
                        ]
                    ]
                ]
            ]);
    }
}
