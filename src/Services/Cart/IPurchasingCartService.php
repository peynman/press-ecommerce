<?php


namespace Larapress\ECommerce\Services\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;

interface IPurchasingCartService
{
    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param int $currency
     *
     * @return Response
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
     * @return Cart
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
     * @return Cart
     */
    public function removeItemFromPurchasingCart(CartContentModifyRequest $request, IECommerceUser $user, ICartItem $cartItem, int $currency);


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     *
     * @return Cart
     */
    public function getPurchasingCart(IECommerceUser $user, int $currency);
}
