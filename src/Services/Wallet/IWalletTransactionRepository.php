<?php

namespace Larapress\ECommerce\Services\Wallet;

use Larapress\CRUD\Services\Pagination\PaginatedResponse;

interface IWalletTransactionRepository {
    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     *
     * @return PaginatedResponse
     */
    public function getWalletTransactionsPaginated($user, $page = 0, $limit = null);
}
