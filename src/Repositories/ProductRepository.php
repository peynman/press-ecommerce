<?php

namespace Larapress\ECommerce\Repositories;

use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Larapress\CRUD\Services\BaseCRUDService;
use Larapress\CRUD\Services\ICRUDService;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\ProductCategory;
use Larapress\ECommerce\Models\ProductType;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Profiles\Models\Form;
use Larapress\Profiles\Models\FormEntry;
use Larapress\Profiles\Repository\Domain\IDomainRepository;

class ProductRepository implements IProductRepository
{
    /**
     *
     * @return array
     */
    public function getProdcutCateogires($user)
    {
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
    public function getProdcutCategoryChildren($user, $category)
    {
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
    public function getRootProductCategories($user)
    {
        return ProductCategory::with([
            'children',
        ])->whereNull('parent_id')->get();
    }

    /**
     *
     * @return array
     */
    public function getProductTypes($user)
    {
        return ProductType::all();
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @return void
     */
    public function getProductNames($user)
    {
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
     * @return array
     */
    public function getProductsPaginated($user, $page = 0, $limit = 50, $categories = [], $types = [])
    {
        $query = $this->getProductsPaginatedQuery($user, $page, $categories, $types);
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
                $item['available'] = in_array($item['id'], $purchases) || $item->isFree();
            }
        }

        return BaseCRUDService::formatPaginatedResponse([], $resultset);
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param [type] $categories
     * @return array
     */
    public function getPurchasedProductsPaginated($user, $page = 0, $limit = 30, $categories = [], $types = [])
    {
        $query = $this->getPurchasedProductsPaginatedQuery($user, $page, $categories, $types);
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
     * @return Cart[]
     */
    public function getPurchasedProdutsCarts($user)
    {
        $carts = Cart::query()
            ->with(['products', 'products.types'])
            ->where('customer_id', $user->id)
            ->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED])
            ->where('flags', '&', Cart::FLAG_USER_CART)
            ->orderBy('updated_at', 'desc')
            ->get();

        return $carts;
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param [type] $cart_id
     * @return Cart
     */
    public function getCartForUser($user, $cart_id) {
        return Cart::query()
            ->with(['products', 'products.types'])
            ->where('customer_id', $user->id)
            ->where('id', $cart_id)
            ->first();
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param [type] $product_id
     * @return void
     */
    public function getProductDetails($user, $product_id)
    {
        $product = Product::with([
            'children' => function ($q) {
                $q->orderBy('priority', 'desc');
            },
            'children.children' => function ($q) {
                $q->orderBy('priority', 'desc');
            },
            'types',
            'categories',
            'children.types',
            'children.categories',
            'children.children.types',
            'children.children.categories',
        ])->find($product_id);

        if (is_null($product)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        /** @var IBankingService */
        $service = app(IBankingService::class);
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getCurrentRequestDomain();
        $purchases = $service->getPurchasedItemIds($user, $domain);

        $product['available'] = in_array($product->id, $purchases) || $product->isFree();
        $children = $product['children'];
        foreach ($children as &$child) {
            $child['available'] = $product['available'] || in_array($child->id, $purchases) || $child->isFree();

            if (isset($child->data['types']['session']['sendForm']) && isset($child->data['types']['session']['sendForm'])) {
                $child['sent_forms'] = FormEntry::query()
                                            ->where('user_id', $user->id)
                                            ->where('form_id', config('larapress.ecommerce.lms.course_file_upload_default_form_id'))
                                            ->where('tags', 'course-'.$child->id.'-taklif')
                                            ->first();
            }

            if ($child->children) {
                $inners = $child->children;
                foreach ($inners as &$inner) {
                    $inner['available'] = $product['available'] ||  $child['available'] || in_array($inner->id, $purchases) || $inner->isFree();
                }
            }
        }

        return $product;
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param integer $page
     * @param integer $limit
     * @param array $categories
     * @param array $types
     * @return Builder
     */
    protected function getProductsPaginatedQuery($user, $page = 0, $categories = [], $types = [])
    {
        Paginator::currentPageResolver(
            function () use ($page) {
                return $page;
            }
        );

        $query = Product::query()->with(['categories', 'types']);
        if (count($categories) > 0) {
            $query->whereHas('categories', function ($q) use ($categories) {
                $q->whereIn('id', $categories);
            });
        }

        if (count($types) > 0) {
            $query->whereHas('types', function ($q) use ($types) {
                $q->whereIn('name', $types);
            });
        }

        $query->orderBy('priority', 'desc');
        return $query;
    }

    protected function getPurchasedProductsPaginatedQuery($user, $page = 0, $categories = [], $types = [])
    {
        $query = $this->getProductsPaginatedQuery($user, $page, $categories, $types);

        /** @var IBankingService */
        $service = app(IBankingService::class);
        /** @var IDomainRepository */
        $domainRepo = app(IDomainRepository::class);
        $domain = $domainRepo->getCurrentRequestDomain();
        $purchases = $service->getPurchasedItemIds($user, $domain);

        $query->where(function ($query) use ($purchases) {
            $query->orWhere(function ($q) use ($purchases) {
                $q->orWhereIn('id', $purchases);
                $q->orWhereIn('parent_id', $purchases);
            })->orWhere(function ($q) {
                $q->whereRaw("JSON_EXTRACT(data, '$.pricing[0].amount') = 0");
            });
        });

        return $query;
    }
}
