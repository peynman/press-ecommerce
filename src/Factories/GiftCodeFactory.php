<?php

namespace Larapress\ECommerce\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Larapress\ECommerce\Models\GiftCode;

class GiftCodeFactory extends Factory
{
    protected $model = GiftCode::class;

    public function definition()
    {
        return [
            'amount' => 50000,
            'author_id' => 1,
            'currency' => config('larapress.ecommerce.banking.currency.id'),
            'code' => $this->faker->password,
            'flags' => 0,
            'data' => [
                'type' => 'percent',
                'value' => $this->faker->numberBetween(10, 50),
            ],
        ];
    }

    public function limitProducts($ids)
    {
        return $this->state(function ($attrs) use ($ids) {
            return array_merge($attrs, [
                'data' => array_merge($attrs['data'], [
                    'products' => $ids,
                ])
            ]);
        });
    }


    public function limitFixedProductsOnly($only = true)
    {
        return $this->state(function ($attrs) use ($only) {
            return array_merge($attrs, [
                'data' => array_merge($attrs['data'], [
                    'fixed_only' => $only,
                ])
            ]);
        });
    }

    public function limitUsers($ids)
    {
        return $this->state(function ($attrs) use ($ids) {
            return array_merge($attrs, [
                'data' => array_merge($attrs['data'], [
                    'specific_ids' => $ids,
                ])
            ]);
        });
    }

    public function limitMinAmount($amount)
    {
        return $this->state(function ($attrs) use ($amount) {
            return array_merge($attrs, [
                'data' => array_merge($attrs['data'], [
                    'specific_ids' => $amount,
                ])
            ]);
        });
    }

    public function limitCategories($ids)
    {
        return $this->state(function ($attrs) use ($ids) {
            return [
                'data' => array_merge($attrs['data'], [
                    'productCategories' => $ids,
                ])
            ];
        });
    }
    public function limitMinItems($count)
    {
        return $this->state(function ($attrs) use ($count) {
            return array_merge($attrs, [
                'data' => array_merge($attrs['data'], [
                    'min_items' => $count,
                ])
            ]);
        });
    }


    public function makePassive()
    {
        return $this->state(function ($attrs) {
            return array_merge($attrs, [
                'flags' => GiftCode::FLAGS_PASSIVE,
            ]);
        });
    }


}
