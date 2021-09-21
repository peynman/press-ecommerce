<?php

namespace Larapress\ECommerce\Services\Cart;

use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Services\Cart\Requests\CartInstallmentUpdateRequest;

interface IInstallmentCartService
{

    /**
     * Undocumented function
     *
     * @return ICart[]
     */
    public function updateCartInstallmentsForPeriodicPurchases();


    /**
     * Undocumented function
     *
     * @return ICart[]
     */
    public function updateCartInstallmentsForUser(IECommerceUser $user);

    /**
     * Undocumented function
     *
     * @param ICart $cart
     *
     * @return ICart
     */
    public function updateInstallmentsForCart(ICart $cart);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     *
     * @return ICart
     */
    public function updateSingleInstallmentsCarts(IECommerceUser $user);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     *
     * @return ICart[]
     */
    public function getUserInstallments(IECommerceUser $user);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param int|ICart $cart
     *
     * @return ICart
     */
    public function updatePeriodicPaymentCart(IECommerceUser $user, $cart, CartInstallmentUpdateRequest $request);
}
