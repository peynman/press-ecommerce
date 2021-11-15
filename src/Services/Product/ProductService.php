<?php

namespace Larapress\ECommerce\Services\Product;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Larapress\CRUD\Events\CRUDCreated;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\IECommerceUser;
use Larapress\FileShare\Models\FileUpload;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\ProductType;
use Larapress\ECommerce\Services\Cart\ICartService;
use Larapress\ECommerce\Services\Product\Requests\ProductCategoryModifyRequest;
use Larapress\ECommerce\Services\Product\Requests\ProductCloneRequest;

class ProductService implements IProductService
{
    /**
     * Undocumented function
     *
     * @param ProductCloneRequest $request
     *
     * @return Product
     */
    public function cloneProductForRequest(ProductCloneRequest $request)
    {
        /** @var Product */
        $product = Product::with(['author', 'types', 'cateogires'])->find($request->getProductID());

        if (is_null($product)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        $data = $product->toArray();
        $data['name'] = Helpers::randomString(10);
        unset($data['id']);
        /** @var Product */
        $duplicate = Product::create($data);

        $types = $product->types->pluck('id');
        $duplicate->types()->sync($types);

        $categories = $product->categories->pluck('id');
        $duplicate->categories()->sync($categories);

        $duplicate['author'] = $product->author;
        $duplicate['types'] = $product->types;
        $duplicate['categories'] = $product->categories;

        CRUDCreated::dispatch(Auth::user(), $duplicate, ProductCRUDProvider::class, Carbon::now());

        return $duplicate;
    }

    /**
     * Undocumented function
     *
     * @param array $productIds
     * @param array $categoryIds
     * @param string $mode
     *
     * @return Product[]
     */
    public function modifyProductCategory(array $productIds, array $categoryIds, string $mode)
    {
        switch ($mode) {
            case ProductCategoryModifyRequest::MODE_SYNC_CATEGORY:
                DB::table('product_category_pivot')
                    ->whereIn('product_id', $productIds)
                    ->delete();
                $inserts = [];
                foreach ($productIds as $productId) {
                    foreach ($categoryIds as $categoryId) {
                        $inserts[] = [
                            'product_id' => $productId,
                            'product_category_id' => $categoryId,
                        ];
                    }
                }
                DB::table('product_category_pivot')->insert($inserts);
                break;
            case ProductCategoryModifyRequest::MODE_REMOVE_CATEGORY:
                DB::table('product_category_pivot')
                    ->whereIn('product_id', $productIds)
                    ->whereIn('product_category_id', $categoryIds)
                    ->delete();
                break;
            case ProductCategoryModifyRequest::MODE_ADD_CATEGORY:
                $inserts = [];
                foreach ($productIds as $productId) {
                    foreach ($categoryIds as $categoryId) {
                        $inserts[] = [
                            'product_id' => $productId,
                            'product_category_id' => $categoryId,
                        ];
                    }
                }
                DB::table('product_category_pivot')->insert($inserts);
                break;
        }

        return Product::with('categories')
            ->whereIn('id', $productIds)
            ->get();
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int|Product $product
     * @param int|FileUpload $link
     * @param callable $callback
     * @return mixed
     */
    public function checkProductLinkAccess(IECommerceUser $user, $product, $link, $callback)
    {
        /** @var ICartService $service */
        $service = app()->make(ICartService::class);

        if (is_numeric($product)) {
            /** @var Product $product */
            $product = Product::with('types')->find($product);
        }

        if ($product->isFree() || (!is_null($user) && $service->isProductOnPurchasedList($user, $product))) {
            /** @var ProductType[] */
            $typeDatas = $product->data['types'];
            $file_ids = [];
            foreach ($typeDatas as $typeData) {
                if (isset($typeData['file_id'])) {
                    $file_ids[] = $typeData['file_id'];
                }
                if (isset($typeData['files']) && is_array($typeData['files'])) {
                    foreach ($typeData['files'] as $fileMeta) {
                        $file_ids[] = $fileMeta['file'];
                    }
                }
                if (isset($typeData['extras']) && is_array($typeData['extras'])) {
                    foreach ($typeData['extras'] as $extra) {
                        if (isset($extra['file_id'])) {
                            $file_ids[] = $extra['file_id'];
                        }
                        if (isset($extra['files']) && is_array($extra['files'])) {
                            foreach ($extra['files'] as $fileMeta) {
                                $file_ids[] = $fileMeta['file'];
                            }
                        }
                    }
                }
            }

            $link_id = is_numeric($link) ? $link : $link->id;
            if (!in_array($link_id, $file_ids)) {
                throw new AppException(AppException::ERR_INVALID_QUERY);
            }
        } else {
            throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
        }


        if (is_numeric($link)) {
            $link = FileUpload::find($link);
        }
        if (is_null($link)) {
            throw new AppException(AppException::ERR_OBJ_FILE_NOT_FOUND);
        }

        return $callback($product, $link);
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param int|Product $product
     * @param closure $callback
     *
     * @return mixed
     */
    public function checkProductAccess(IECommerceUser $user, $product, $callback)
    {
        /** @var ICartService $service */
        $service = app()->make(ICartService::class);

        if (is_numeric($product)) {
            /** @var Product $product */
            $product = Product::with('types')->find($product);
        }

        if ($product->isFree() || (!is_null($user) && $service->isProductOnPurchasedList($user, $product))) {
            /** @var ProductType[] */
            $typeDatas = $product->data['types'];
            $file_ids = [];
            foreach ($typeDatas as $typeData) {
                if (isset($typeData['file_id'])) {
                    $file_ids[] = $typeData['file_id'];
                }
            }
        } else {
            throw new AppException(AppException::ERR_OBJ_ACCESS_DENIED);
        }

        return $callback($product);
    }
}
