<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Http\Response;
use Larapress\CRUD\Services\CRUD\CRUDController;
use Larapress\ECommerce\Services\Product\IProductService;
use Larapress\ECommerce\Services\Product\Requests\ProductCategoryModifyRequest;
use Larapress\ECommerce\Services\Product\Requests\ProductCloneRequest;

/**
 * Standard CRUD Controller for Product resource.
 *
 * @group Product Management
 */
class ProductController extends CRUDController
{
    /**
     * Clone Product
     *
     * @return Response
     */
    public function duplicateProduct(IProductService $service, ProductCloneRequest $request)
    {
        return $service->cloneProductForRequest($request);
    }

    /**
     * Undocumented function
     *
     * @param IProductService $service
     * @param ProductCategoryModifyRequest $request
     *
     * @return Response
     */
    public function modifyProductCategory(IProductService $service, ProductCategoryModifyRequest $request)
    {
        return $service->modifyProductCategory(
            $request->getProductIds(),
            $request->getCategoryIds(),
            $request->getMode()
        );
    }
}
