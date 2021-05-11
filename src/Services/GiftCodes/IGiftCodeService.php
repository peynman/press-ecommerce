<?php

namespace Larapress\ECommerce\Services\GiftCodes;

use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\ECommerce\IECommerceUser;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\Services\Cart\CartGiftDetails;

interface IGiftCodeService {

    /**
     * Undocumented function
     *
     * @param int|GiftCode $giftCode
     * @param int $count
     * @return array
     */
    public function cloneGiftCode($giftCode, $count);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param Cart $cart
     * @param string $code
     * @return CartGiftDetails|null
     * @throws AppException
     */
     public function getGiftUsageDetailsForCart(IECommerceUser $user, Cart $cart, string $code);

     /**
      * Undocumented function
      *
      * @param IECommerceUser $user
      * @param Cart $cart
      * @param GiftCode $gift
      * @return Cart
      */
     public function markGiftCodeUsageForCart(IECommerceUser $user, Cart $cart, GiftCode $gift);
}
