<?php

namespace Larapress\ECommerce;

use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\Profiles\Models\FormEntry;

trait BaseECommerceUser {


    /**
     * Undocumented function
     *
     * @return void
     */
    public function getIntroducerDataAttribute() {
        return Helpers::getCachedValue(
            'larapress.users.'.$this->id.'.introducer',
            function () {
                $entry = $this->form_entries()
                                ->where('form_id', config('larapress.ecommerce.lms.introducer_default_form_id'))
                                ->first();
                if (! is_null($entry)) {
                    $introducer_id = explode('-',$entry->tags)[2];
                    $class = config('larapress.crud.user.class');
                    $introducer = call_user_func([$class, 'find'], $introducer_id);
                    return [$introducer, $entry];
                }
            },
            ['user:'.$this->id, 'forms', 'support'],
            null
        );
    }


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function carts()
    {
        return $this->hasMany(
            Cart::class,
            'customer_id',
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function purchases() {
        return $this->carts()->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function purchase_cart() {
        return $this->carts()->whereIn('status', [Cart::STATUS_UNVERIFIED]);
    }
}
