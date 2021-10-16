<?php

namespace Larapress\ECommerce\Services\Product;

use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\CRUD\Services\Pagination\PaginatedResponse;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\ProductCategory;
use Larapress\ECommerce\Models\ProductType;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\ProductReview;
use Larapress\ECommerce\Services\Cart\ICartService;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository implements IProductRepository
{

    /** @var ICartService */
    protected $cartService;
    public function __construct(ICartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     *
     * @return array
     */
    public function getProdcutCategories($user)
    {
        return ProductCategory::with([
            'children' => function ($q) {
                $q->orderBy('data->order', 'desc');
            },
            'children.children' => function ($q) {
                $q->orderBy('data->order', 'desc');
            },
        ])
            ->orderBy('data->order', 'desc')
            ->get();
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param string $category
     *
     * @return array
     */
    public function getProdcutCategoryChildren($user, $category)
    {
        return ProductCategory::with([
            'children' => function ($q) {
                $q->orderBy('data->order', 'desc');
            },
            'children.children' => function ($q) {
                $q->orderBy('data->order', 'desc');
            },
        ])
            ->where('name', $category)
            ->orderBy('data->order', 'desc')
            ->first();
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     *
     * @return array
     */
    public function getRootProductCategories($user)
    {
        return ProductCategory::with([
            'children' => function ($q) {
                $q->orderBy('data->order', 'desc');
            },
            'children.children' => function ($q) {
                $q->orderBy('data->order', 'desc');
            },
        ])
            ->whereNull('parent_id')
            ->orderBy('data->order', 'desc')
            ->get();
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
     * @param IECommerceUser $user
     *
     * @return array
     */
    public function getProductNames($user, $types = [])
    {
        if (is_null($user)) {
            return [];
        }
        $query = Product::query()->select('id', 'name');
        $this->applyPublishExpireWindow($query);
        if (count($types) > 0) {
            $query->whereHas('types', function ($q) use ($types) {
                $q->whereIn('name', $types);
            });
        }

        return $query->get();
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     *
     * @return Cart[]
     */
    public function getPurchasedProductsCarts($user)
    {
        $carts = Cart::query()
            ->with(['products', 'products.types'])
            ->where('customer_id', $user->id)
            ->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED])
            ->orderBy('updated_at', 'desc')
            ->get();

        return $carts;
    }

    /**
     * Undocumented function
     *
     * @param Product $product
     *
     * @return Product[]
     */
    public function getProductAncestorIds($product)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.product-ancestors.' . $product->id,
            ['product:' . $product->id],
            86400,
            true,
            function () use ($product) {
                $ancestors = [];
                $parent = $product->parent;
                while (!is_null($parent)) {
                    $ancestors[] = $parent->id;
                    $parent = $parent->parent;
                }
                return $ancestors;
            },
        );
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param int $productId
     * @param int $page
     * @param int|null $limit
     *
     * @return PaginatedResponse
     */
    public function getProductReviews($user, $productId, $page = 1, $limit = null)
    {
        $limit = PaginatedResponse::safeLimit($limit);

        return new PaginatedResponse(
            ProductReview::query()
                ->where('product_id', $productId)
                ->whereNotNull('message')
                ->where(function ($q) use ($user) {
                    $q->where('flags', '&', ProductReview::FLAGS_PUBLIC);
                    if (!is_null($user)) {
                        $q->orWhere('author_id', $user->id);
                    }
                })
                ->paginate($limit, ['*'], 'page', $page)
        );
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param int $product_id
     *
     * @return array
     */
    public function getProductDetails($user, $product_id)
    {
        /** @var Product */
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

        if (!is_null($user)) {
            $product['available'] = $this->cartService->isProductOnPurchasedList($user, $product) || $product->isFree();
            $product['locked'] = $this->cartService->isProductOnLockedList($user, $product);
        } else {
            $product['available'] = $product->isFree();
            $product['locked'] = false;
        }
        /** @var Product[] */
        $children = $product['children'];

        foreach ($children as &$child) {
            if (!is_null($user)) {
                $child['available'] = $product['available'] || $this->cartService->isProductOnPurchasedList($user, $child);
                $child['locked'] = ($product['locked'] || $this->cartService->isProductOnLockedList($user, $child)) && !$child->isFree();
            } else {
                $child['available'] = $product->isFree() || $child->isFree();
                $child['locked'] = false;
            }

            if ($child->children) {
                $inners = $child->children;
                foreach ($inners as &$inner) {
                    if (!is_null($user)) {
                        $inner['available'] = $product['available'] ||  $child['available'] || $this->cartService->isProductOnPurchasedList($user, $inner);
                        $inner['locked'] = ($product['locked'] || $child['locked'] || $this->cartService->isProductOnLockedList($user, $inner)) && !$inner->isFree();
                    } else {
                        $inner['available'] = $product->isFree() || $child->isFree() || $inner->isFree();
                        $inner['locked'] = false;
                    }
                }
            }
        }

        return $product;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $page
     * @param integer $limit
     * @param array $categories
     * @param array $types
     * @param boolean $exclude
     *
     * @return PaginatedResponse
     */
    public function getProductsPaginated(
        $user,
        $page = 1,
        $limit = null,
        $inCategories = [],
        $withTypes = [],
        $sortBy = null,
        $notIntCatgories = [],
        $withoutTypes = []
    ) {
        $query = $this->getProductsPaginatedQuery($user, $page, $inCategories, $withTypes, $notIntCatgories, $withoutTypes, false, $sortBy);
        $limit = PaginatedResponse::safeLimit($limit);
        $r = new PaginatedResponse($query->paginate($limit));
        return $r;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param page $limit
     * @param number $limit
     * @param array $categories
     * @param array $types
     *
     * @return PaginatedResponse
     */
    public function getPurchasedProductsPaginated(
        $user,
        $page = 1,
        $limit = null,
        $inCategories = [],
        $withTypes = [],
        $sortBy = null,
        $notIntCatgories = [],
        $withoutTypes = []
    ) {
        $query = $this->getProductsPaginatedQuery($user, $page, $inCategories, $withTypes, $notIntCatgories, $withoutTypes, true, $sortBy);
        $limit = PaginatedResponse::safeLimit($limit);
        return new PaginatedResponse($query->paginate($limit));
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $page
     * @param integer $limit
     * @param array $categories
     * @param array $types
     * @param array $notIntCatgories // excluded categories
     * @return Builder
     */
    protected function getProductsPaginatedQuery(
        $user,
        $page = 1,
        $inCategories = [],
        $withTypes = [],
        $notIntCatgories = [],
        $withoutTypes = [],
        $purchased = false,
        $sortBy = null,
    ) {
        Paginator::currentPageResolver(
            function () use ($page) {
                return $page;
            }
        );

        $query = Product::query()->with(['categories', 'types']);
        if (count($inCategories) > 0) {
            $query->whereHas('categories', function ($q) use ($inCategories) {
                $q->whereIn('id', $inCategories);
            });
        }
        if (count($notIntCatgories) > 0) {
            $query->whereDoesntHave('categories', function ($q) use ($notIntCatgories) {
                $q->whereIn('id', $notIntCatgories);
            });
        }

        if (count($withTypes) > 0) {
            $query->whereHas('types', function ($q) use ($withTypes) {
                $q->whereIn('id', $withTypes);
            });
        }
        if (count($withoutTypes) > 0) {
            $query->whereDoesntHave('types', function ($q) use ($withoutTypes) {
                $q->whereIn('id', $withoutTypes);
            });
        }

        if ($purchased) {
            $purchases = is_null($user) ? [] : $this->cartService->getPurchasedItemIds($user);
            $query->where(function ($query) use ($purchases) {
                $query->orWhere(function ($q) use ($purchases) {
                    $q->orWhereIn('id', $purchases);
                    $q->orWhereIn('parent_id', $purchases);
                })->orWhere(function ($q) {
                    $q->whereNull('parent_id');
                    $q->orWhereRaw("NULLIF(JSON_UNQUOTE(JSON_EXTRACT(data, '$.fixedPrice.amount')), 'null') IS NULL");
                });
            });
        }

        $query = $this->applyPublishExpireWindow($query);

        if (!is_null($sortBy)) {
            $sorters = config('larapress.ecommerce.products.sorts');
            $sorterNames = array_keys($sorters);
            if (in_array($sortBy, $sorterNames)) {
                $sorterClass = $sorters[$sortBy];
                /** @var IProductSort */
                $sorter = new $sorterClass();
                $sorter->applySort($query);
            } else {
                throw new AppException(AppException::ERR_INVALID_QUERY);
            }
        } else {
            $query->orderBy('priority', 'desc');
        }

        return $query;
    }

    /**
     * Undocumented function
     *
     * @param Builder $query
     * @return Builder
     */
    protected function applyPublishExpireWindow($query)
    {
        $query->where(function ($q) {
            $q->whereNull('publish_at');
            $q->orWhereDate('publish_at', '<=', Carbon::now());
        });
        $query->where(function ($q) {
            $q->whereNull('expires_at');
            $q->orWhereDate('expires_at', '>', Carbon::now());
        });
        return $query;
    }
}
