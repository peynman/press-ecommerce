<?php

namespace Larapress\ECommerce;

use Illuminate\Support\Facades\Cache;
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
    public function getSupportUserProfileAttribute() {
        return Helpers::getCachedValue(
            'larapress.users.'.$this->id.'.support',
            function () {
                $entry = $this->form_entries()
                                ->where('form_id', config('larapress.profiles.defaults.support-registration-form-id'))
                                ->first();
                if (!is_null($entry)) {
                    $taggedSupportId = explode('-', $entry->tags)[2];
                    $profile = FormEntry::where('user_id', $taggedSupportId)
                                ->where('form_id', config('larapress.profiles.defaults.profile-support-form-id'))
                                ->first();
                    if (isset($profile->data['values']['firstname']) && isset($profile->data['values']['lastname'])) {
                        $data = $profile->data;
                        $data['values']['fullname'] = $profile->data['values']['firstname'].' '.$profile->data['values']['lastname'];
                        $profile->data = $data;
                    }
                    return $profile;
                }
                return null;
            },
            ['user.support:'.$this->id],
            null
        );
    }

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
            ['user.introducer:'.$this->id],
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
