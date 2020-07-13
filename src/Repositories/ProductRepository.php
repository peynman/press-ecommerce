<?php

namespace Larapress\ECommerce\Repositories;

use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Larapress\CRUD\Base\BaseCRUDService;
use Larapress\CRUD\Base\ICRUDService;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\ProductCategory;
use Larapress\ECommerce\Models\ProductType;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Profiles\Repository\Domain\IDomainRepository;

class ProductRepository implements IProductRepository {
    /**
     *
     * @return array
     */
    public function getProdcutCateogires($user) {
        return ProductCategory::with([
            'children',
        ])->get();
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param [type] $category
     * @return array
     */
    public function getProdcutCategoryChildren($user, $category) {
        return ProductCategory::with([
            'children',
        ])->where('name', $category)->first();
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @return void
     */
    public function getRootProductCategories($user) {
        return ProductCategory::with([
            'children',
        ])->whereNull('parent_id')->get();
    }

    /**
     *
     * @return array
     */
    public function getProductTypes($user) {
        return ProductType::all();
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @return void
     */
    public function getProductNames($user) {
        if (is_null($user)) {
            return [];
        }

        return Product::select('id', 'name')->get();
    }

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param [type] $page
     * @return void
     */
    public function getProductsPaginated($user, $page = 0, $limit = 50, $categories = [], $types = []) {
        $query = $this->getProductsPaginatedQuery($user, $page, $limit, $categories, $types);
        $resultset = $query->paginate($limit);
        if (!is_null($user)) {
            /** @var IBankingService */
            $service = app(IBankingService::class);
            /** @var IDomainRepository */
            $domainRepo = app(IDomainRepository::class);
            $domain = $domainRepo->getCurrentRequestDomain();

            $items = $resultset->items();
            $purchases = $service->getPurchasedItemIds($user, $domain);
            foreach ($items as $item) {
                $item['available'] = in_array($item['id'], $purchases);
            }
        }

        return BaseCRUDService::formatPaginatedResponse([], $resultset);
    }



    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param [type] $categories
     * @return void
     */
    public function getPurchasedProductsPaginated($user, $page = 0, $limit = 30, $categories = [], $types = []) {
        $query = $this->getProductsPaginatedQuery($user, $page, $limit, $categories, $types);

        /** @var IBankingService */
        $service = app(IBankingService::class);
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getCurrentRequestDomain();
        $purchases = $service->getPurchasedItemIds($user, $domain);

        $query->whereIn('id', $purchases);
        $resultset = $query->paginate($limit);
        $items = $resultset->items();
        foreach ($items as $item) {
            $item['available'] = true;
        }

        return BaseCRUDService::formatPaginatedResponse([], $resultset);
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param [type] $product_id
     * @return void
     */
    public function getProductDetails($user, $product_id) {

    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param integer $page
     * @param integer $limit
     * @param array $categories
     * @param array $types
     * @return void
     */
    protected function getProductsPaginatedQuery($user, $page = 0, $limit = 50, $categories = [], $types = []) {
        Paginator::currentPageResolver(
            function () use ($page) {
                return $page;
            }
        );

        $query = Product::query()->with(['categories', 'types']);
        if (count($categories) > 0) {
            $query->whereHas('categories', function ($q) use($categories) {
                $q->whereIn('id', $categories);
            });
        }

        if (count($types) > 0) {
            $query->whereHas('types', function ($q) use($types) {
                $q->whereIn('name', $types);
            });
        }

        $query->orderBy('priority', 'desc');
        return $query;
    }
}
