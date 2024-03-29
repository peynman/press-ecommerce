<?php

namespace Larapress\ECommerce\Services\Cart;

use Larapress\CRUD\Services\Pagination\PaginatedResponse;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\Cart\ICartRepository as CartICartRepository;
use Larapress\Profiles\IProfileUser;

class CartRepository implements CartICartRepository
{
    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param integer $page
     * @param int|null $limit
     *
     * @return array
     */
    public function getPurchasedCartsPaginated(IProfileUser $user, $page = 1, $limit = null)
    {
        $limit = PaginatedResponse::safeLimit($limit);

        return new PaginatedResponse(
            Cart::query()
                ->with(['products'])
                ->where('customer_id', $user->id)
                ->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED])
                ->orderBy('updated_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page)
        );
    }
}
