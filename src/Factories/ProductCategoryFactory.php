<?php

namespace Larapress\ECommerce\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Larapress\ECommerce\Models\ProductCategory;

class ProductCategoryFactory extends Factory {
    protected $model = ProductCategory::class;

    public function definition()
    {
        $title = $this->faker->words(2, true);
        return [
            'name' => str_replace(' ', '-', strtolower($title)),
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


    public function parent($parentId) {
        return $this->state(function ($attrs) use ($parentId) {
            return [
                'parent_id' => $parentId
            ];
        });
    }
}
