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

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param [type] $category
     * @return array
     */
    public function getProdcutCategoryChildren($user, $category);

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @return ProductCategory[]
     */
    public function getRootProductCategories($user);

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param [type] $categories
     * @return array
     */
    public function getProductsPaginated($user, $page = 0, $limit = 30, $categories = [], $types = []);


    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param [type] $categories
     * @return array
     */
    public function getPurchasedProductsPaginated($user, $page = 0, $limit = 30, $categories = [], $types = []);


    /**
     * Undocumented function
     *
     * @param [type] $user
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
}
