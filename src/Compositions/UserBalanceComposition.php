<?php

namespace Larapress\ECommerce\Compositions;

use Illuminate\Database\Eloquent\Builder;
use Larapress\CRUD\Services\CRUD\CRUDProviderComposition;

class UserBalanceComposition extends CRUDProviderComposition
{
    /**
     * Undocumented function
     *
     * @return array
     */
    public function getValidRelations(): array
    {
        return array_merge(parent::getValidRelations(), [
            'wallet_balance' => config('larapress.ecommerce.routes.wallet_transactions.provider'),
            'carts' => config('larapress.ecommerce.routes.carts.provider'),
            'wallet_transactions' => config('larapress.ecommerce.routes.wallet_transactions.provider'),
        ]);
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getFilterFields(): array
    {
        return array_merge(parent::getFilterFields(), [
            'balance_less' => function (Builder $query, $value) {
                $query->whereHas('wallet_balance', function($q) use($value) {
                    $q->selectRaw('user_id, sum(amount) as balance');
                    $q->groupBy('user_id');
                    $q->having('balance', '<', $value);
                });
            },
            'balance_more' => function (Builder $query, $value) {
                $query->whereHas('wallet_balance', function($q) use($value) {
                    $q->selectRaw('user_id, sum(amount) as balance');
                    $q->groupBy('user_id');
                    $q->having('balance', '>', $value);
                });
            },
            'balance' => function (Builder $query, $value) {
                $query->whereHas('wallet_balance', function($q) use($value) {
                    $q->selectRaw('user_id, sum(amount) as balance');
                    $q->groupBy('user_id');
                    $q->having('balance', '=', $value);
                });
            },
        ]);
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getDefaultShowRelations(): array
    {
        return array_merge(parent::getDefaultShowRelations(), [
            'wallet_balance',
            'purchase_cart.products',
        ]);
    }
}
