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
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function form_profile_default();

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function form_profile_support();

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getBalanceAttribute();


    /**
     * Undocumented function
     *
     * @return array
     */
    public function getOwenedProductsIds();

    /**
     * Undocumented function
     *
     * @return null|FormEntry
     */
    public function getProfileAttribute();
}
