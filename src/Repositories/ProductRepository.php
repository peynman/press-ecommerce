<?php

namespace Larapress\ECommerce\Repositories;

use Larapress\ECommerce\Models\ProductCategory;
use Larapress\ECommerce\Models\ProductType;

class ProductRepository implements IProductRepository {
    /**
     *
     * @return array
     */
    public function getProdcutCateogires($user) {
        return ProductCategory::with([
            'children',
            'children.children',
            'children.children.children'
        ])->get();
    }

    /**
     *
     * @return array
     */
    public function getProductTypes($user) {
        return ProductType::all();
    }
}
