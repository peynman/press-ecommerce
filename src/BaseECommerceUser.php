<?php

namespace Larapress\ECommerce;

use Illuminate\Support\Facades\Cache;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Profiles\Models\FormEntry;

trait BaseECommerceUser {
    /**
     * Undocumented function
     *
     * @return void
     */
    public function getSupportUserProfileAttribute() {
        if (isset($this->cache['support'])) {
            return  $this->cache['support'];
        }

        return null;
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
                        $profile['entry'] = $entry;
                    }
                    return $profile;
                }
                return null;
            },
            ['user.support:'.$this->id],
            null
        );
    }

    /** @var IBankingService */
    static $bankingService = null;
    public function getBalanceAttribute() {
        if (isset($this->cache['balance'])) {
            return $this->cache['balance'];
        }

        return null;
        return Helpers::getCachedValue(
            'larapress.users.'.$this->id.'.balance-attr',
            function () {
                return [
                    'amount' => $this->wallet()
                        ->where('currency', config('larapress.ecommerce.banking.currency.id'))
                        ->sum('amount'),
                    'currency' => config('larapress.ecommerce.banking.currency'),
                ];
            },
            ['user.wallet:'.$this->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getIntroducerDataAttribute() {
        if (isset($this->cache['introducer'])) {
            return $this->cache['introducer'];
        }

        return null;
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

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function wallet() {
        return $this->hasMany(
            WalletTransaction::class,
            'user_id',
        );
    }

    /**
     * Undocumented function
     *
     * @param nullable $property
     * @return void
     */
    public function updateUserCache($property = null, $values = []) {
        $fastCache = $this->cache;
        if (is_null($fastCache)) {
            $fastCache = [];
        }

        $fastUpdaters = [
            'balance' => function() use(&$fastCache) {
                // update internal fast cache! for balance
                $fastCache = array_merge($fastCache, [
                    'balance' => [
                        'amount' => WalletTransaction::query()
                                    ->where('user_id', $this->id)
                                    ->where('currency', config('larapress.ecommerce.banking.currency.id'))
                                    ->sum('amount'),
                        'currency' => config('larapress.ecommerce.banking.currency'),
                        'default_gateway' => config('larapress.ecommerce.banking.default_gateway')
                    ]
                ]);
            },
            'profile' => function() use(&$fastCache) {
                $entry = null;
                // if this role has custom form-id for its profiles, use it.
                $profileRoles = self::getProfilesRoleMap();
                foreach ($profileRoles as $role => $formId) {
                    if ($this->hasRole($role)) {
                        $entry = $this->form_entries()->where('form_id', $formId)->first();
                    }
                }
                // else use default form-id from config
                if (is_null($entry)) {
                    $entry = $this->form_entries()->where('form_id', config('larapress.profiles.defaults.profile-form-id'))->first();
                }
                $fastCache = array_merge($fastCache, [
                    'profile' => $entry
                ]);
            },
            'support' => function() use(&$fastCache) {
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
                        $profile['entry'] = $entry;
                    }
                    $entry = $profile;
                }

                $fastCache = array_merge($fastCache, [
                    'support' => $entry
                ]);
            },
            'introducer' => function() use(&$fastCache) {
                $entry = $this->form_entries()
                            ->where('form_id', config('larapress.ecommerce.lms.introducer_default_form_id'))
                            ->first();
                if (! is_null($entry)) {
                    $introducer_id = explode('-',$entry->tags)[2];
                    $class = config('larapress.crud.user.class');
                    $introducer = call_user_func([$class, 'find'], $introducer_id);
                    $fastCache = array_merge($fastCache, [
                        'introducer' => [$introducer, $entry]
                    ]);
                }
            }
        ];

        if (is_null($property)) {
            foreach ($fastUpdaters as $name => $updater) {
                $updater();
            }
        } else {
            if (isset($values[$property])) {
                $fastCache = array_merge($fastCache, [
                    $property => $values[$property]
                ]);
            } else if (isset($fastUpdaters[$property])) {
                $fastUpdaters[$property]();
            }
        }

        $this->cache = $fastCache;
        $this->update();
    }
}
