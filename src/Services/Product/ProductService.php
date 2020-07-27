<?php

namespace Larapress\ECommerce\Services\Product;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Events\CRUDCreated;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\ProductType;
use Larapress\ECommerce\Repositories\IProductRepository;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Profiles\Repository\Domain\IDomainRepository;
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
     * @param Request $request
     * @param int $product_id
     * @return Product
     */
    public function duplicateProductForRequest(Request $request, $product_id)
    {
        /** @var Product */
        $product = Product::find($product_id);

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

        CRUDCreated::dispatch($duplicate, ProductCRUDProvider::class, Carbon::now());

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
            'product.' . $product_id . '.sales',
            function () use ($product_id) {
                /** @var IMetricsService */
                $service = app(IMetricsService::class);

                /** @var ICRUDUser|IProfileUser */
                $user = Auth::user();
                $domains = [];
                if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
                    $domains = $user->getAffiliateDomainIds();
                }

                $amount = $service->sumMeasurement('product.' . $product_id . '.sales_amount', $domains);
                $periodic = $service->sumMeasurement('product.' . $product_id . '.sales_periodic', $domains);
                $fixed = $service->sumMeasurement('product.' . $product_id . '.sales_fixed', $domains);

                return [
                    'amount' => $amount,
                    'periodic' => $periodic,
                    'fixed' => $fixed,
                ];
            },
            ['sales:' . $product_id],
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
    public function checkProductLinkAccess(Request $request, $product, $link, $callback) {
        /** @var IBankingService $service */
        $service = app()->make(IBankingService::class);

        /** @var IProfileUser */
        $user = Auth::user();

        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getRequestDomain($request);

        if (is_numeric($product)) {
            /** @var Product $product */
            $product = Product::with('types')->find($product);
        }

		if ($product->isFree() || (!is_null($user) && $service->isProductOnPurchasedList($user, $domain, $product))) {
            /** @var ProductType[] */
            $typeDatas = $product->data['types'];
            $file_ids = [];
			foreach ($typeDatas as $typeData) {
                if (isset($typeData['file_id'])) {
                    $file_ids[] = $typeData['file_id'];
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

        return $callback($request, $product, $link);
    }
}