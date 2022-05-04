<?php

namespace Larapress\ECommerce\Tests\Feature;

use Larapress\CRUD\Tests\CustomerTestApplication;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\ECommerce\Models\ProductCategory;
use Larapress\ECommerce\Models\ProductType;

class CartPromotionTest extends CustomerTestApplication
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
        $cat0 = ProductCategory::factory()->create();
        $cat1 = ProductCategory::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $p = Product::factory()
                    ->addPrice((10-$i)*1000, config('larapress.ecommerce.banking.currency.id'))
                    ->quantized()
                    ->create();
            $p->types()->attach($type);
            if ($i < 5) {
                $p->categories()->attach($cat0);
            } else {
                $p->categories()->attach($cat1);
            }
        }

        $this->customer = $this->createVerifiedCustomer('sample-tester', 'sample-tester', '091211111111');

        $this->giftCode = GiftCode::factory()
            ->limitProducts([9])
            ->limitCategories([$cat0->id])
            ->limitMinItems(4)
            ->makePassive()
            ->create();
    }

    public function testCartPromotionSuccess()
    {
        /** @var Product */
        $p1 = Product::find(1);
        /** @var Product */
        $p2 = Product::find(2);
        /** @var Product */
        $p3 = Product::find(3);
        /** @var Product */
        $p4 = Product::find(4);
        /** @var Product */
        $p5 = Product::find(5);
        /** @var Product */
        $p6 = Product::find(6);
        /** @var Product */
        $p7 = Product::find(7);
        /** @var Product */
        $p8 = Product::find(8);

        $cartItems = [
            1 => [1, 10000],
            2 => [3, 9000*2 + 10000],
            3 => [4, 8000*2 + 9000*3 + 10000],
            4 => [1, 8000*3 + 9000*3 + 10000],
            5 => [1, 8000*4 + 9000*3 + 10000],
            6 => [2, 5000*2 + 8000*4 + 9000*3 + 10000],
            7 => [2, 4000*2 + 5000*2 + 8000*4 + 9000*3 + 10000],
            9 => [2, 4000*2 + 5000*2 + 7000 + 8000*4 + 9000*3 + 10000],
            8 => [3, 3000*3 + 4000*2 + 5000*2 + 7000 + 8000*4 + 9000*3 + 10000],
        ];

        $this->be($this->customer);
        $this->app['auth']->guard('api')->setUser($this->customer);

        foreach ($cartItems as $itemId => [$itemQuantity, $cartAmount]) {
            $this->postJson('api/me/current-cart/add', [
                'productId' => $itemId,
                'currency' => config('larapress.ecommerce.banking.currency.id'),
                'quantity' => $itemQuantity,
            ])
                ->assertStatus(200)
                ->assertJson([
                    'amount' => $cartAmount,
                ]);
        }
    }
}
