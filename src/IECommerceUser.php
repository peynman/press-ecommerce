<?php

namespace Larapress\ECommerce;

use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\FormEntry;

interface IECommerceUser extends IProfileUser
{
/**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function carts();

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function purchases();

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function purchase_cart();

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function wallet();

    /**
     * Undocumented function
     *
     * @return void
     */
    public function wallet_balance();

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getOwenedProductsIds();
}
