<?php

namespace Larapress\ECommerce\Services\Promotions;

use Larapress\ECommerce\Models\GiftCode;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Services\Cart\ICart;

interface IPromotionService {
    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param ICart $cart
     *
     * @return GiftCode[]
     */
     public function getAvailablePromotionsForCart(IECommerceUser $user, ICart $cart);

     /**
      * Undocumented function
      *
      * @param IECommerceUser $user
      * @param ICart $cart
      * @param array $promotions
      *
      * @return ICart
      */
     public function markPromotionUsageForCart(IECommerceUser $user, ICart $cart, array $promotions): ICart;
}
