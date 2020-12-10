<?php

namespace Larapress\ECommerce\Repositories;

use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Services\BaseCRUDService;
use Larapress\CRUD\Services\ICRUDService;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Services\BaseCRUDProvider;
use Larapress\CRUD\Services\ICRUDProvider;
use Larapress\CRUD\Services\IPermissionsMetadata;
use Larapress\ECommerce\CRUD\ProductCRUDProvider;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\ProductCategory;
use Larapress\ECommerce\Models\ProductType;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Profiles\Models\Form;
use Larapress\Profiles\Models\FormEntry;
use Larapress\Profiles\Repository\Domain\IDomainRepository;
use Larapress\ECommerce\IECommerceUser;

class ProductRepository implements IProductRepository
{
    /**
     *
     * @return array
     */
    public function getProdcutCateogires($user)
    {
        return ProductCategory::with([
            'children' => function ($q) {
                $q->orderBy('data->order', 'desc');
            },
        ])->orderBy('data->order', 'desc')->get();
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
            'children' => function ($q) {
                $q->orderBy('data->order', 'desc');
            },
        ])->where('name', $category)->orderBy('data->order', 'desc')->first();
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
            'children' => function ($q) {
                $q->orderBy('data->order', 'desc');
            },
        ])->whereNull('parent_id')->orderBy('data->order', 'desc')->get();
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
     * @param IProfileUser $user
     * @return WalletTransaction[]
     */
    public function getWalletTransactionsForUser($user) {
        return WalletTransaction::where('user_id', $user->id)->orderBy('id', 'desc')->get();
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @return void
     */
    public function getProductNames($user, $types = [])
    {
        if (is_null($user)) {
            return [];
        }
        $query = Product::query()->select('id', 'name');
        $this->applyPublishExpireWindow($query);
        if (count($types) > 0) {
            $query->whereHas('types', function($q) use($types) {
                $q->whereIn('name', $types);
            });
        }

        return $query->get();
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
     * @return array
     */
    public function getProductsPaginated($user, $page = 0, $limit = 50, $categories = [], $types = [], $exclude = false)
    {
        $query = $this->getProductsPaginatedQuery($user, $page, $categories, $types, $exclude);
        $resultset = $query->paginate($limit);
        if (!is_null($user)) {
            /** @var IBankingService */
            $service = app(IBankingService::class);
            $items = $resultset->items();
            $purchases = is_null($user) ? [] : $service->getPurchasedItemIds($user);
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

        /** @var IBankingService */
        $service = app(IBankingService::class);
        $locked = $service->getPeriodicInstallmentsLockedProducts($user);

        foreach ($items as $item) {
            $item['available'] = true;
            if (in_array($item->id, $locked)) {
                $item['locked'] = true;
            }
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
            ->where('flags', '&', Cart::FLAGS_USER_CART|Cart::FLAGS_ADMIN)
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
     * @param IECommerceUser $user
     * @param [type] $product_id
     * @return void
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

        /** @var IBankingService */
        $service = app(IBankingService::class);
        $purchases = is_null($user) ? [] : $service->getPurchasedItemIds($user);
        $locked = is_null($user) ? [] : $service->getPeriodicInstallmentsLockedProducts($user);

        $product['available'] = in_array($product->id, $purchases) || $product->isFree();
        $product['locked'] = in_array($product->id, $locked) && !$product->isFree();
        $children = $product['children'];

        foreach ($children as &$child) {
            $child['available'] = $product['available'] || in_array($child->id, $purchases) || $child->isFree();
            $child['locked'] = $product['locked'] && !$child->isFree();

            if (isset($child->data['types']['session']['sendForm'])) {
                $child['sent_forms'] = FormEntry::query()
                                            ->where('user_id', $user->id)
                                            ->where('form_id', config('larapress.ecommerce.lms.course_file_upload_default_form_id'))
                                            ->where('tags', 'course-'.$child->id.'-taklif')
                                            ->first();
            }

            if (isset($child->data['types']['azmoon']['is_required'])) {
                $child['azmoon_result'] = FormEntry::query()
                                        ->where('user_id', $user->id)
                                        ->where('form_id', config('larapress.ecommerce.lms.azmoon_result_form_id'))
                                        ->where('tags', 'azmoon-'.$child->id)
                                        ->first();
            }

            if ($child->children) {
                $inners = $child->children;
                foreach ($inners as &$inner) {
                    $inner['available'] = $product['available'] ||  $child['available'] || in_array($inner->id, $purchases) || $inner->isFree();
                }
            }
        }

        if (!is_null($user)) {
            if ($user->hasPermission(config('larapress.ecommerce.routes.products.name').'.'.IPermissionsMetadata::REPORTS)) {
                $product->sales_fixed;
                $product->sales_periodic;
                $product->sales_periodic_payment;
                $product->sales_real_amount;
                $product->sales_virtual_amount;
            }
        }

        return $product;
    }

    /**
     *
     */
    protected function getPurchasedProductsPaginatedQuery($user, $page = 0, $categories = [], $types = [])
    {
        $query = $this->getProductsPaginatedQuery($user, $page, $categories, $types);

        /** @var IBankingService */
        $service = app(IBankingService::class);
        $purchases = is_null($user) ? [] : $service->getPurchasedItemIds($user);

        $query->where(function ($query) use ($purchases) {
            $query->orWhere(function ($q) use ($purchases) {
                $q->orWhereIn('id', $purchases);
                $q->orWhereIn('parent_id', $purchases);
            })->orWhere(function ($q) {
                $q->whereRaw("JSON_EXTRACT(data, '$.pricing[0].amount') = 0");
            });
        });
        $this->applyPublishExpireWindow($query);

        return $query;
    }

    /**
     * Undocumented function
     *
     * @param [type] $user
     * @param integer $page
     * @param integer $limit
     * @param array $categories
     * @param array $types
     * @param bool $exclude // exclude categories instead
     * @return Builder
     */
    protected function getProductsPaginatedQuery($user, $page = 0, $categories = [], $types = [], $exclude = false)
    {
        Paginator::currentPageResolver(
            function () use ($page) {
                return $page;
            }
        );

        if (is_numeric($categories)) {
            $categories = [$categories];
        }

        $query = Product::query()->with(['categories', 'types']);
        if (count($categories) > 0) {
            if ($exclude) {
                $query->whereDoesntHave('categories', function ($q) use ($categories) {
                    $q->whereIn('id', $categories);
                });
            } else {
                $query->whereHas('categories', function ($q) use ($categories) {
                    $q->whereIn('id', $categories);
                });
            }
        }

        if (count($types) > 0) {
            $query->whereHas('types', function ($q) use ($types) {
                $q->whereIn('name', $types);
            });
        }

        $query = $this->applyPublishExpireWindow($query);
        $query->orderBy('priority', 'desc');
        return $query;
    }

    protected function applyPublishExpireWindow($query) {
        /*
        $query->where(function ($q) {
            $q->whereNull('publish_at');
            $q->orWhereDate('publish_at', '<=', Carbon::now());
        });
        $query->where(function ($q) {
            $q->whereNull('expires_at');
            $q->orWhereDate('expires_at', '>', Carbon::now());
        });
        */
        return $query;
    }
}
