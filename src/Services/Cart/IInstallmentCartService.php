<?php


namespace Larapress\ECommerce\Services\Cart;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;

interface IInstallmentCartService
{

    /**
     * Undocumented function
     *
     * @return Cart[]
     */
    public function updateCartInstallmentsForPeriodicPurchases();

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return Cart[]
     */
    public function getUserInstallments(IECommerceUser $user);
}
