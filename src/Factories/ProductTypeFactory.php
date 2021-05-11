<?php

namespace Larapress\ECommerce\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Larapress\ECommerce\Models\ProductType;

class ProductTypeFactory extends Factory {
    protected $model = ProductType::class;

    public function definition()
    {
        $title = $this->faker->jobTitle;
        return [
            'name' => strtolower($title),
            'author_id' => 1,
            'flags' => 0,
            'data' => [
                'title' => $title,
            ]
        ];
    }

    public function author($userId) {
        return $this->state(function ($attrs) use ($userId) {
            return [
                'author_id' => $userId
            ];
        });
    }
}
