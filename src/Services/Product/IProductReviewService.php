<?php

namespace Larapress\ECommerce\Services\Product;

use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\ProductReview;

interface IProductReviewService {
    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param int|Product $product
     *
     * @return ProductReview
     */
    public function updateReaction (IECommerceUser $user, $product, $reaction);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param int|Product $product
     * @param string|null $review
     * @param int|null $stars
     * @param array $data
     *
     * @return ProductReview
     */
    public function giveReview(IECommerceUser $user, $product, $review = null, $stars = null, $data = []);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param int|ProductReview $review
     * @param string $reviewMessage
     * @param int $stars
     * @param array $data
     *
     * @return ProductReview
     */
    public function editReview(IECommerceUser $user, $review, $reviewMessage = null, $stars = null, $data = []);
}
