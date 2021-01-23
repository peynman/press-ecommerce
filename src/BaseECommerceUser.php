<?php

namespace Larapress\ECommerce;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\SupportGroup\FormEntryUserSupportProfileRelationship;
use Larapress\Profiles\Models\FormEntry;
use Illuminate\Support\Str;
use Larapress\CRUD\Models\Role;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Product\UserSalesAmountRelationship;
use Larapress\ECommerce\Services\Product\UserSalesCountRelationship;

trait BaseECommerceUser
{
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
    public function purchases()
    {
        return $this->carts()->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function purchase_cart()
    {
        return $this->carts()->whereIn('status', [Cart::STATUS_UNVERIFIED]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function wallet()
    {
        return $this->hasMany(
            WalletTransaction::class,
            'user_id',
        );
    }


    public function wallet_balance()
    {
        return $this->wallet()
            ->selectRaw('user_id, sum(amount) as balance')
            ->where('currency', config('larapress.ecommerce.banking.currency.id'))
            ->where('type', '!=', WalletTransaction::TYPE_UNVERIFIED)
            ->groupBy('user_id');
    }

    /**
     * Undocumented function
     *
     * @return FormEntry
     */
    public function getProfileAttribute()
    {
        if ($this->hasRole(['support', 'support-external'])) {
            return $this->form_profile_support;
        }

        return $this->form_profile_default;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getOwenedProductsIds()
    {
        $ownerEntries = $this->form_entries()
            ->where('form_id', config('larapress.ecommerce.lms.teacher_support_form_id'))
            ->get()
            ->map(function (FormEntry $entry) {
                return intval(\Illuminate\Support\Str::substr($entry->tags, strlen('product-')));
            })->toArray();
        $childIds = Product::select('id')->whereIn('parent_id', $ownerEntries)->pluck('id')->toArray();
        $childChildIds = Product::select('id')->whereIn('parent_id', $childIds)->pluck('id')->toArray();

        return array_merge($ownerEntries, $childIds, $childChildIds);
    }

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function form_profile_default()
    {
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
    public function form_profile_support()
    {
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
    public function form_support_registration_entry()
    {
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
    public function getSupportUserId()
    {
        if (!is_null($this->form_support_registration_entry)) {
            $tags = $this->form_support_registration_entry->tags;
            if (Str::startsWith($tags, 'support-group-')) {
                return Str::substr($tags, Str::length('support-group-'));
            }
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @return Carbon|null
     */
    public function getSupportUserStartedDate() {
        if (!is_null($this->form_support_registration_entry)) {
            if (isset($this->form_support_registration_entry->data['values']['support_ids'])) {
                $suppIds = $this->form_support_registration_entry->data['values']['support_ids'];
                if (count($suppIds) > 0 && isset($suppIds[count($suppIds) - 1]['updated_at'])) {
                    return Carbon::parse($suppIds[count($suppIds) - 1]['updated_at']);
                }
            } else {
                return $this->form_support_registration_entry->created_at;
            }
        }

        return null;
    }


    /**
     * Undocumented function
     *
     * @return Role|null
     */
    public function getSupportUserRole() {
        $userRole = DB::table('user_role')->where('user_id', $this->getSupportUserId())->first();
        if (!is_null($userRole)) {
            return Role::find($userRole->role_id);
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function form_support_introducer_entry()
    {
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
    public function form_support_user_profile()
    {
        return new FormEntryUserSupportProfileRelationship($this);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getSupportUserProfileAttribute()
    {
        return $this->form_support_user_profile;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function sales_fixed() {
        return new UserSalesCountRelationship(
            $this,
            'sales_fixed',
            []
        );
    }


    /**
     * Undocumented function
     *
     * @return array
     */
    public function sales_periodic() {
        return new UserSalesCountRelationship(
            $this,
            'sales_periodic',
            []
        );
    }

    public function sales_virtual() {
        return new UserSalesAmountRelationship(
            $this,
            WalletTransaction::TYPE_VIRTUAL_MONEY,
            []
        );
    }


    public function sales_real() {
        return new UserSalesAmountRelationship(
            $this,
            WalletTransaction::TYPE_REAL_MONEY,
            []
        );
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getBalanceAttribute()
    {
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
    public function getIntroducerDataAttribute()
    {
        return Helpers::getCachedValue(
            'larapress.users.' . $this->id . '.introducer',
            function () {
                $entry = $this->form_entries()
                    ->where('form_id', config('larapress.ecommerce.lms.introducer_default_form_id'))
                    ->first();
                if (!is_null($entry)) {
                    $introducer_id = explode('-', $entry->tags)[2];
                    $class = config('larapress.crud.user.class');
                    $introducer = call_user_func([$class, 'find'], $introducer_id);
                    return [$introducer, $entry];
                }
            },
            ['user.introducer:' . $this->id],
            null
        );
    }
}
