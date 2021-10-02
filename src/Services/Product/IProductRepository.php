<?php

namespace Larapress\ECommerce\Services\Product;

use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\ProductCategory;
use Larapress\CRUD\Services\Pagination\PaginatedResponse;

interface IProductRepository
{
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

    /**
     * Undocumented function
     *
     * @param IECommerceUser|null $user
     * @param int $category
     *
     * @return ProductCategory
     */
    public function getProdcutCategoryChildren($user, $category);

    /**
     * Undocumented function
     *
     * @param IECommerceUser|null $user
     * @return ProductCategory[]
     */
    public function getRootProductCategories($user);

    /**
     * Undocumented function
     *
     * @param IECommerceUser|null $user
     * @param integer $page
     * @param integer $limit
     * @param array $categories
     * @param array $types
     * @param boolean $exclude
     *
     * @return PaginatedResponse
     */
    public function getProductsPaginated(
        $user,
        $page = 1,
        $limit = null,
        $inCategories = [],
        $withTypes = [],
        $sortBy = null,
        $notIntCatgories = [],
        $withoutTypes = []
    );

    /**
     * Undocumented function
     *
     * @param IECommerceUser|null $user
     * @param int $categories
     *
     * @return PaginatedResponse
     */
    public function getPurchasedProductsPaginated(
        $user,
        $page = 1,
        $limit = null,
        $inCategories = [],
        $withTypes = [],
        $sortBy = null,
        $notIntCatgories = [],
        $withoutTypes = []
    );

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param int $productId
     * @param int $page
     * @param int|null $limit
     *
     * @return PaginatedResponse
     */
    public function getProductReviews(
        $user,
        $productId,
        $page = 1,
        $limit = null
    );

    /**
     * Undocumented function
     *
     * @param IECommerceUser|null $user
     * @return Cart[]
     */
    public function getPurchasedProductsCarts($user);

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param [type] $product_id
     * @return Product
     */
    public function getProductDetails($user, $product_id);

    /**
     * Undocumented function
     *
     * @param Product $product
     *
     * @return Product[]
     */
    public function getProductAncestorIds($product);
}
