<?php

namespace Larapress\ECommerce\Services\Product;

use Illuminate\Http\Request;

interface IProductService {

    /**
     * Undocumented function
     *
     * @param Request $request
     * @return array
     */
    public function queryProductsFromRequest(Request $request);


    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $product_id
     * @return Product
     */
    public function duplicateProductForRequest(Request $request, $product_id);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int|Product $product
     * @param int|FileUpload $link
     * @param callable $callback
     * @return mixed
     */
    public function checkProductLinkAccess(Request $request, $product, $link, $callback);


    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int|Product $product
     * @param callbable $callback
     * @return mixed
     */
    public function checkProductAccess(Request $request, $product, $callback);
}
