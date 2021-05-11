<?php

namespace Larapress\ECommerce\Services\Product;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Events\CRUDCreated;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\IECommerceUser;
use Larapress\FileShare\Models\FileUpload;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\ProductType;
use Larapress\ECommerce\Repositories\IProductRepository;
use Larapress\ECommerce\Services\Cart\ICartService;
use Larapress\Reports\Services\IMetricsService;
use Larapress\Profiles\IProfileUser;

class ProductService implements IProductService
{
    /**
     * Undocumented function
     *
     * @param Request $request
     * @return array
     */
    public function queryProductsFromRequest(Request $request)
    {
        /** @var IProductRepository */
        $repo = app(IProductRepository::class);

        if ($request->get('purchased', false)) {
            return $repo->getPurchasedProductsPaginated(
                Auth::user(),
                $request->get('page', 1),
                $request->get('limit', config('larapress.ecommerce.repository.per_page', 50)),
                $request->get('categories', []),
                $request->get('types', [])
            );
        }

        return $repo->getProductsPaginated(
            Auth::user(),
            $request->get('page', 1),
            $request->get('limit', config('larapress.ecommerce.repository.per_page', 50)),
            $request->get('categories', []),
            $request->get('types', [])
        );
    }

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
     * @param int $product_id
     * @return array
     */
    public function getProductSales($product_id)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.product.' . $product_id . '.sales',
            function () use ($product_id) {
                /** @var IMetricsService */
                $service = app(IMetricsService::class);

                /** @var IProfileUser */
                $user = Auth::user();
                $domains = [];
                if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
                    $domains = $user->getAffiliateDomainIds();
                }

                $virtual = $service->sumMeasurement('product.' . $product_id . '.sales.1.amount', $domains);
                $real = $service->sumMeasurement('product.' . $product_id . '.sales.2.amount', $domains);

                $periodic = $service->sumMeasurement('product.' . $product_id . '.sales_periodic', $domains);
                $fixed = $service->sumMeasurement('product.' . $product_id . '.sales_fixed', $domains);
                $periodic_payment = $service->sumMeasurement('product.' . $product_id . '.periodic_payment', $domains);

                return [
                    'real' => $real,
                    'virtual' => $virtual,
                    'periodic' => $periodic,
                    'fixed' => $fixed,
                    'periodic_payment' => $periodic_payment
                ];
            },
            ['product.sales:' . $product_id],
            null
        );
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
