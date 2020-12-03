<?php

namespace Larapress\ECommerce;

use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\SupportGroup\FormEntryUserSupportProfileRelationship;
use Larapress\Profiles\Models\FormEntry;
use Illuminate\Support\Str;

trait BaseECommerceUser {
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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function wallet() {
        return $this->hasMany(
            WalletTransaction::class,
            'user_id',
        );
    }


    public function wallet_balance() {
        return $this->wallet()
            ->selectRaw('user_id, sum(amount) as balance')
            ->where('currency', config('larapress.ecommerce.banking.currency.id'))
            ->where('type', '!=', WalletTransaction::TYPE_UNVERIFIED)
            ->groupBy('user_id');
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getProfileAttribute() {
        if ($this->hasRole(config('larapress.profiles.security.roles.affiliate'))) {
            return $this->form_profile_support;
        }

        return $this->form_profile_default;
    }

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function form_profile_default() {
        return $this->hasOne(
            FormEntry::class,
            'user_id'
        )->where('form_id', config('larapress.ecommerce.lms.profile_form_id'));
    }

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function form_profile_support() {
        return $this->hasOne(
            FormEntry::class,
            'user_id'
        )->where('form_id', config('larapress.ecommerce.lms.support_profile_form_id'));
    }

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function form_support_registration_entry() {
        return $this->hasOne(
            FormEntry::class,
            'user_id'
        )->where('form_id', config('larapress.ecommerce.lms.support_group_default_form_id'));
    }


    /**
     * Undocumented function
     *
     * @return null|int
     */
    public function getSupportUserId() {
        if (!is_null($this->form_support_registration_entry)) {
            $tags = $this->form_support_registration_entry->tags;
            if (Str::startsWith($tags, 'supporg-group-')) {
                return Str::substr($tags, Str::length('supporg-group-'));
            }
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function form_support_introducer_entry() {
        return $this->hasOne(
            FormEntry::class,
            'user_id'
        )->where('form_id', config('larapress.ecommerce.lms.introducer_default_form_id'));
    }

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function form_support_user_profile() {
        return new FormEntryUserSupportProfileRelationship($this);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getSupportUserProfileAttribute() {
        return $this->form_support_user_profile;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getBalanceAttribute() {
        $wallet_balance = $this->wallet_balance;
        if (count($wallet_balance) === 0) {
            $wallet_balance = 0;
        } else {
            $wallet_balance = $wallet_balance[0]->balance;
        }
        return [
            'amount' => $wallet_balance,
            'currency' => config('larapress.ecommerce.banking.currency'),
            'default_gateway' => config('larapress.ecommerce.banking.default_gateway'),
        ];
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
}
