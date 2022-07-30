<?php

namespace Larapress\ECommerce;

use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\Profiles\Models\FormEntry;
use Larapress\ECommerce\Models\Product;

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
        return $this->hasOne(
            Cart::class,
            'customer_id',
        )
        ->whereIn('status', [Cart::STATUS_UNVERIFIED])
        ->where('flags', '&', Cart::FLAGS_USER_CART) // is a user cart
        ->whereRaw('(flags & ' . Cart::FLGAS_FORWARDED_TO_BANK . ') = 0') // has never been forwarded to bank page
        ->orderBy('id', 'DESC');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function paid_carts()
    {
        return $this->carts()
            ->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE])
            ->whereRaw('(flags & ' . Cart::FLAGS_PERIOD_PAYMENT_CART . ') = 0');
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
     * @return array
     */
    public function getOwenedProductsIds()
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $this->id . '.owned_products',
            ['form-entries:' . $this->id],
            3600,
            true,
            function () {
                $ownerEntries = $this->form_entries()
                    ->where('form_id', config('larapress.ecommerce.product_owner_form_id'))
                    ->get()
                    ->map(function (FormEntry $entry) {
                        return intval(\Illuminate\Support\Str::substr($entry->tags, strlen('product-')));
                    })->toArray();
                $childIds = Product::select('id')->whereIn('parent_id', $ownerEntries)->pluck('id')->toArray();
                $childChildIds = Product::select('id')->whereIn('parent_id', $childIds)->pluck('id')->toArray();

                return array_merge($ownerEntries, $childIds, $childChildIds);
            }
        );
    }
}
