<?php

namespace Larapress\ECommerce\Services\Product;

use Larapress\ECommerce\IECommerceUser;

interface IProductService
{

    /**
     * Undocumented function
     *
     * @param ProductQueryRequest $request
     *
     * @return array
     */
    public function queryProductsFromRequest(ProductQueryRequest $request);


    /**
     * Undocumented function
     *
     * @param ProductCloneRequest $request

     * @return Product[]
     */
    public function cloneProductForRequest(ProductCloneRequest $request);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param int|Product $product
     * @param int|FileUpload $link
     * @param callable $callback
     * @return mixed
     */
    public function checkProductLinkAccess(IECommerceUser $user, $product, $link, $callback);


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param int|Product $product
     * @param closure $callback
     *
     * @return mixed
     */
    public function checkProductAccess(IECommerceUser $user, $product, $callback);
}
