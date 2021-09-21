<?php

namespace Larapress\ECommerce\Services\Product;

use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\ProductReview;
use Larapress\ECommerce\Services\Cart\ICartService;

class ProductReviewService implements IProductReviewService
{
    /**
     * Undocumented function
     *
     * @param IECommerceUser|null $user
     * @param int|Product $product
     * @param string $reaction
     *
     * @return ProductReview
     */
    public function updateReaction($user, $product, $reaction)
    {
        if (is_numeric($product)) {
            /** @var Product */
            $product = Product::find($product);
        }

        if (is_null($user) && config('larapress.ecommerce.product_reviews.review_users_only')) {
            throw new AppException(AppException::ERR_ACCESS_DENIED);
        }

        if (config('larapress.ecommerce.product_reviews.review_purchased_product') === true) {
            /** @var ICartService */
            $cartService = app(ICartService::class);
            if (is_null($user) || !$cartService->isProductOnPurchasedList($user, $product) || $product->isFree()) {
                throw new AppException(AppException::ERR_ACCESS_DENIED);
            }
        }

        /** @var ProductReview */
        $review = ProductReview::updateOrCreate([
            'author_id' => is_null($user) ? null : $user->id,
            'product_id' => $product->id,
            'message' => null,
        ], [
            'data' => [
                'reaction' => $reaction
            ],
        ]);

        return $review;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser|null $user
     * @param int|Product $product
     * @param string $review
     * @param int|null $stars
     * @param array $data
     *
     * @return ProductReview
     */
    public function giveReview($user, $product, $review = null, $stars = null, $data = [])
    {
        if (is_numeric($product)) {
            /** @var Product */
            $product = Product::find($product);
        }

        if (is_null($user) && config('larapress.ecommerce.product_reviews.review_users_only')) {
            throw new AppException(AppException::ERR_ACCESS_DENIED);
        }

        if (config('larapress.ecommerce.product_reviews.review_purchased_product') === true) {
            /** @var ICartService */
            $cartService = app(ICartService::class);
            if (is_null($user) || !$cartService->isProductOnPurchasedList($user, $product) || $product->isFree()) {
                throw new AppException(AppException::ERR_ACCESS_DENIED);
            }
        }

        /** @var ProductReview */
        $review = ProductReview::create([
            'author_id' => is_null($user) ? null : $user->id,
            'product_id' => $product->id,
            'stars' => $stars,
            'message' => $review,
            'flags' => config('larapress.ecommerce.product_reviews.review_auto_confirm') ? ProductReview::FLAGS_PUBLIC : 0,
            'data' => $data,
        ]);

        return $review;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser|null $user
     * @param int|ProductReview $review
     * @param string $reviewMessage
     * @param int $stars
     * @param array $data
     *
     * @return ProductReview
     */
    public function editReview($user, $review, $reviewMessage = null, $stars = null, $data = [])
    {
        if (is_numeric($review)) {
            /** @var Product */
            $review = ProductReview::find($review);
        }

        if (is_null($user) && config('larapress.ecommerce.product_reviews.review_users_only')) {
            throw new AppException(AppException::ERR_ACCESS_DENIED);
        }

        if (is_null($user) || $user->id !== $review->author_id) {
            throw new AppException(AppException::ERR_ACCESS_DENIED);
        }

        if (!is_null($review)) {
            $review->update([
                'message' => $reviewMessage,
                'stars' => $stars,
                'data' => array_merge($review->data ?? [], $data),
            ]);
        }

        return $review;
    }
}
