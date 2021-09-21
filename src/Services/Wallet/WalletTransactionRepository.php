<?php

namespace Larapress\ECommerce\Services\Wallet;

use Larapress\CRUD\Services\Pagination\PaginatedResponse;
use Larapress\ECommerce\Models\WalletTransaction;

class WalletTransactionRepository implements IWalletTransactionRepository
{
    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     *
     * @return WalletTransaction[]
     */
    public function getWalletTransactionsPaginated($user, $page = 0, $limit = null)
    {
        $limit = PaginatedResponse::safeLimit($limit);

        return new PaginatedResponse(
            WalletTransaction::query()
                ->where('user_id', $user->id)
                ->orderBy('id', 'desc')
                ->paginate($limit, ['*'], 'page', $page)
        );
    }
}
