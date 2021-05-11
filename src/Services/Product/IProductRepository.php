<?php

namespace Larapress\ECommerce\Services\Product;
use Larapress\ECommerce\IECommerceUser;

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
     * @return array
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
     * @return array
     */
    public function getProductsPaginated($user, $page = 0, $limit = 50, $categories = [], $types = [], $exclude = false);

    /**
     * Undocumented function
     *
     * @param IECommerceUser|null $user
     * @param int $categories
     *
     * @return array
     */
    public function getPurchasedProductsPaginated($user, $page = 0, $limit = 30, $categories = [], $types = []);

    /**
     * Undocumented function
     *
     * @param IECommerceUser|null $user
     * @return Cart[]
     */
    public function getPurchasedProdutsCarts($user);

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param [type] $cart_id
     * @return Cart
     */
    public function getCartForUser($user, $cart_id);

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
     * @param IProfileUser $user
     * @return WalletTransaction[]
     */
    public function getWalletTransactionsForUser($user);

    /**
     * Undocumented function
     *
     * @param Product $product
     *
     * @return array
     */
    public function getProductAncestorIds($product);
}
