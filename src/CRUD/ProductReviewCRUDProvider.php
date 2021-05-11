<?php

namespace Larapress\ECommerce\CRUD;

use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Services\CRUD\BaseCRUDProvider;
use Larapress\CRUD\Services\CRUD\ICRUDProvider;
use Larapress\CRUD\Services\RBAC\IPermissionsMetadata;
use Larapress\ECommerce\Models\ProductReview;
use Larapress\ECommerce\IECommerceUser;

class ProductReviewCRUDProvider implements ICRUDProvider, IPermissionsMetadata
{
    use BaseCRUDProvider;

    public $name_in_config = 'larapress.ecommerce.routes.product_reviews.name';
    public $model = ProductReview::class;
    public $verbs = [
        self::VIEW,
        self::CREATE,
        self::EDIT,
        self::DELETE,
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
        'created_at',
        'updated_at',
        'flags',
    ];
    public $validRelations = [
        'author',
    ];
    public $defaultShowRelations = [
        'author',
    ];

    public function onBeforeCreate($args)
    {
        $args['author_id'] = Auth::user()->id;

        return $args;
    }

    /**
     * Undocumented function
     *
     * @param ProductReview $object
     * @return bool
     */
    public function onBeforeAccess($object)
    {
        /** @var IECommerceUser $user */
        $user = Auth::user();
        if (! $user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            return $user->id === $object->author_id;
        }

        return true;
    }

    /**
     * @param Builder $query
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function onBeforeQuery($query)
    {
        /** @var ICRUDUser $user */
        $user = Auth::user();
        if (!$user->hasRole(config('larapress.profiles.security.roles.super-role'))) {
            $query->where('author_id', $user->id);
        }

        return $query;
    }
}
