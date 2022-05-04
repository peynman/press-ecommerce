<?php

namespace Larapress\ECommerce\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\ProductCategory;
use Larapress\ECommerce\Models\ProductType;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        $title = $this->faker->words(5, true);
        return [
            'name' => str_replace(' ', '-', strtolower($title)),
            'author_id' => 1,
            'flags' => 0,
            'data' => [
                'title' => $title,
            ],
        ];
    }

    public function author(int $userId)
    {
        return $this->state(function ($attrs) use ($userId) {
            return [
                'author_id' => $userId
            ];
        });
    }

    public function publish_at(Carbon $at)
    {
        return $this->state(function ($attrs) use ($at) {
            return [
                'publish_at' => $at
            ];
        });
    }

    public function expires_at(Carbon $at)
    {
        return $this->state(function ($attrs) use ($at) {
            return [
                'expires_at' => $at
            ];
        });
    }

    public function randomPrice()
    {
        return $this->addPrice($this->faker->numberBetween(1, 20) * 10000, config('larapress.ecommerce.banking.currency.id'), 1);
    }

    public function withType($type)
    {
        return $this->state(function ($attrs) use ($type) {
            return [
                'types' =>  $type
            ];
        });
    }

    public function randomPeriodicPrice()
    {
        return $this->addPeriodicPrice(
            $this->faker->numberBetween(1, 5) * 10000,
            config('larapress.ecommerce.banking.currency.id'),
            1
        )->periodicPriceDetails(
            $this->faker->numberBetween(1, 5) * 10000,
            $this->faker->numberBetween(15, 30),
            $this->faker->numberBetween(3, 5),
            Carbon::now()->addDays($this->faker->numberBetween(30, 90)),
        );
    }

    public function emptyPriceList() {
        return $this->state(function ($attrs) {
            return [
                'data' => array_merge($attrs['data'], [
                    'pricing' => []
                ])
            ];
        });
    }

    public function childOf($id) {
        return $this->state(function ($attrs) use ($id) {
            return [
                'parent_id' => $id,
            ];
        });
    }

    public function freeAccess($free) {
        return $this->state(function ($attrs) use ($free) {
            return [
                'data' => array_merge($attrs['data'], [
                    'free_access' => $free,
                ])
            ];
        });
    }

    public function addPrice(float $amount, int $currency, int $priority = 0)
    {
        return $this->state(function ($attrs) use ($amount, $currency, $priority) {
            return [
                'data' => array_merge($attrs['data'], [
                    'fixedPrice' => [
                        'amount' => $amount,
                        'currency' => $currency,
                        'offAmount' => null,
                    ]
                ])
            ];
        });
    }


    public function addPeriodicPrice(float $amount, int $currency, int $priority = 0)
    {
        return $this->state(function ($attrs) use ($amount, $currency, $priority) {
            return [
                'data' => array_merge($attrs['data'], [
                    'price_periodic' => [
                        [
                            'amount' => $amount,
                            'currency' => $currency,
                            'priority' => $priority,
                        ],
                    ]
                ])
            ];
        });
    }

    public function periodicPriceDetails(float $amount, int $duration, int $count, Carbon $endDate)
    {
        return $this->state(function ($attrs) use ($duration, $amount, $count, $endDate) {
            return [
                'data' => array_merge($attrs['data'], [
                    'calucalte_periodic' => [
                        'period_duration' => $duration,
                        'period_amount' => $amount,
                        'period_count' => $count,
                        'ends_at' => $endDate->format(config('larapress.crud.datetime-format')),
                    ]
                ])
            ];
        });
    }

    public function quantized()
    {
        return $this->state(function ($attrs) {
            return [
                'data' => array_merge($attrs['data'], [
                    'quantized' => true,
                ])
            ];
        });
    }
}
