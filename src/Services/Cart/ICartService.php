<?php


namespace Larapress\ECommerce\Services\Cart;

use Illuminate\Http\Request;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\GiftCode;

interface ICartService
{
    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Carbon|string|null $purchaseTimestamp
     *`
     * @return ICart
     */
    public function markCartPurchased(ICart $cart, $purchaseTimestamp = null);

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Carbon|string|null $postingTimestamp
     * @return ICart
     */
    public function markCartPosted(ICart $cart, $postingTimestamp = null);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param array $ids
     * @param int $currency
     * @param GiftCode|null $name
     * @param int|null $amount
     * @param string|null $desc
     *
     * @return Cart
     */
    public function createCartWithProductIDs(IECommerceUser $user, array $ids, $currency, $giftCode, $amount = null, $desc = null);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     *
     * @return array
     */
    public function getPurchasedItemIds(IECommerceUser $user);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     *
     * @return array
     */
    public function getLockedItemIds(IECommerceUser $user);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     *
     * @return array
     */
    public function getPurchasedCarts(IECommerceUser $user);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer|Product $product
     *
     * @return boolean
     */
    public function isProductOnPurchasedList(IECommerceUser $user, $product);


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer|Product $product
     *
     * @return boolean
     */
    public function isProductOnLockedList(IECommerceUser $user, $product);

    /**
     * Undocumented function
     *
     * @param ICart $cart
     *
     * @return float
     */
    public function calculateCartAmountFromDataAndProducts(ICart $cart);

    /**
     * Undocumented function
     *
     * @param int $userId
     *
     * @return void
     */
    public function resetPurchasedCache($userId);
}
