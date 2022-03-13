<?php


namespace Larapress\ECommerce\Services\Cart;

use Illuminate\Http\Request;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Services\Cart\Requests\CartContentModifyRequest;
use Larapress\ECommerce\Services\Cart\Requests\CartUpdateRequest;
use Larapress\ECommerce\Services\Cart\Requests\CartValidateRequest;

interface IPurchasingCartService
{

    /**
     * Undocumented function
     *
     * @param CartValidateRequest $request
     * @param IECommerceUser $user
     * @param integer $currency
     *
     * @return ICart
     */
    public function validateCartBeforeForwardingToBank(CartValidateRequest $request, IECommerceUser $user, int $currency);

    /**
     * Undocumented function
     *
     * @param string $code
     * @param IECommerceUser $user
     * @param integer $currency
     *
     * @return Cart
     */
    public function updateCartGiftCodeData(string $code, IECommerceUser $user, int $currency);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param int $currency
     *
     * @return ICart
     */
    public function updatePurchasingCart(CartUpdateRequest $request, IECommerceUser $user, int $currency);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param ICartItem $cartItem
     * @param int $currency
     *
     * @return ICart
     */
    public function addItemToPurchasingCart(CartContentModifyRequest $request, IECommerceUser $user, ICartItem $cartItem, int $currency);


    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param ICartItem $cartItem
     * @param int $currency
     *
     * @return ICart
     */
    public function removeItemFromPurchasingCart(CartContentModifyRequest $request, IECommerceUser $user, ICartItem $cartItem, int $currency);


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     *
     * @return ICart
     */
    public function getPurchasingCart(IECommerceUser $user, int $currency);

    /**
     * Undocumented function
     *
     * @param int $userId
     * @return void
     */
    public function resetPurchasingCache($userId);
}
