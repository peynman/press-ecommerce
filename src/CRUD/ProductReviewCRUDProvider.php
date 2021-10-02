<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\CRUD\Traits\CRUDProviderTrait;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDVerb;
use Larapress\CRUD\Services\RBAC\IPermissionsMetadata;
use Larapress\ECommerce\Models\ProductReview;
use Larapress\ECommerce\IECommerceUser;

class ProductReviewCRUDProvider implements ICRUDProvider
{
    use CRUDProviderTrait;

    public $name_in_config = 'larapress.ecommerce.routes.product_reviews.name';
    public $model_in_config = 'larapress.ecommerce.routes.product_reviews.model';
    public $compositions_in_config = 'larapress.ecommerce.routes.product_reviews.compositions';

    public $verbs = [
        ICRUDVerb::VIEW,
        ICRUDVerb::SHOW,
        ICRUDVerb::CREATE,
        ICRUDVerb::EDIT,
        ICRUDVerb::DELETE,
    ];
    public $createValidations = [
        'description' => 'required|string',
        'data' => 'nullable',
        'data.stars' => 'nullable|numeric',
        'flags' => 'nullable|numeric',
    ];
    public $updateValidations = [
        'description' => 'required|string',
        'data' => 'nullable',
        'data.stars' => 'nullable|numeric',
        'flags' => 'nullable|numeric',
    ];
    public $searchColumns = [
        'description',
    ];
    public $validSortColumns = [
        'id',
        'author_id',
        'flags',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getValidRelations(): array
    {
        return [
            'author' => config('larapress.crud.user.provider'),
        ];
    }

    /**
     * Undocumented function
     *
     * @param array $args
     *
     * @return array
     */
    public function onBeforeCreate(array $args): array
    {
        $args['author_id'] = Auth::user()->id;

        return $args;
    }

    /**
     * Undocumented function
     *
     * @param ProductReview $object
     *
     * @return bool
     */
    public function onBeforeAccess($object): bool
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            if ($user->hasRole(config('larapress.ecommerce.products.product_owner_role_ids'))) {
                return in_array($object->id, $user->getOwenedProductsIds());
            }

            return $user->id === $object->author_id;
        }

        return true;
    }

    /**
     * @param Builder $query
     *
     * @return Builder
     */
    public function onBeforeQuery(Builder $query): Builder
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super_role'))) {
            if ($user->hasRole(config('larapress.ecommerce.products.product_owner_role_ids'))) {
                $query->orWhereIn('id', $user->getOwenedProductsIds());
            } else {
                $query->orWhere('author_id', $user->id);
            }
        }

        return $query;
    }
}
