<?php

namespace Larapress\ECommerce\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Larapress\CRUD\Services\CRUD\CRUDController;
use Larapress\ECommerce\Services\Product\IProductReviewService;
use Larapress\ECommerce\Services\Product\Requests\ProductReviewRequest;
use Larapress\ECommerce\IECommerceUser;

/**
 * @group Product Review Management
 */
class ProductReviewController extends CRUDController
{
    public static function registerPublicApiRoutes()
    {
        Route::post(
            config('larapress.ecommerce.routes.product_reviews.name') . '/add',
            '\\' . self::class . '@addProductReview'
        )->name(config('larapress.ecommerce.routes.product_reviews.name') . 'any.add-review');

        Route::post(
            config('larapress.ecommerce.routes.product_reviews.name') . '/edit/{id}',
            '\\' . self::class . '@editProductReview'
        )->name(config('larapress.ecommerce.routes.product_reviews.name') . 'any.edit-review');

        Route::post(
            config('larapress.ecommerce.routes.product_reviews.name') . '/update',
            '\\' . self::class . '@updateProductReaction'
        )->name(config('larapress.ecommerce.routes.product_reviews.name') . 'any.update-reaction');
    }

    /**
     * Undocumented function
     *
     * @param IProductReviewService $service
     * @param ProductReviewRequest $request
     *
     * @return Response
     */
    public function addProductReview(IProductReviewService $service, ProductReviewRequest $request)
    {
        /** @var IECommerceUser */
        $user = Auth::user();
        return [
            'message' => config('larapress.ecommerce.product_reviews.review_auto_confirm') ?
                trans('larapress::ecommerce.products.review.success') : trans('larapress::ecommerce.products.review.success_preview'),
            'review' => $service->giveReview(
                $user,
                $request->getProduct(),
                $request->getReviewMessage(),
                $request->getReviewStars(),
                $request->getReviewData(),
            ),
        ];
    }

    /**
     * Undocumented function
     *
     * @param IProductReviewService $service
     * @param ProductReviewRequest $request
     * @param int $reviewId
     *
     * @return Response
     */
    public function editProductReview(IProductReviewService $service, ProductReviewRequest $request, $reviewId)
    {
        /** @var IECommerceUser */
        $user = Auth::user();
        return [
            'message' => config('larapress.ecommerce.product_reviews.review_auto_confirm') ?
                trans('larapress::ecommerce.products.review.success') : trans('larapress::ecommerce.products.review.success_preview'),
            'review' => $service->editReview(
                $user,
                $reviewId,
                $request->getReviewMessage(),
                $request->getReviewStars(),
                $request->getReviewData(),
            ),
        ];
    }

    /**
     * Undocumented function
     *
     * @param IProductReviewService $service
     * @param ProductReviewRequest $request
     *
     * @return Response
     */
    public function updateProductReaction(IProductReviewService $service, ProductReviewRequest $request)
    {
        /** @var IECommerceUser */
        $user = Auth::user();
        return $service->updateReaction(
            $user,
            $request->getProduct(),
            $request->getReviewReaction(),
        );
    }
}
