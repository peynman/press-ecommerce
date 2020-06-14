<?php

namespace Larapress\ECommerce\Repositories;

interface IProductRepository {
    /**
     *
     * @return array
     */
    public function getProdcutCateogires($user);

    /**
     *
     * @return array
     */
    public function getProductTypes($user);
}
