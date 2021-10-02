<?php

namespace Larapress\ECommerce\Services\Cart;

use Larapress\Profiles\IProfileUser;

interface ICartRepository {
    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param integer $page
     * @param int|null $limit
     *
     * @return array
     */
    public function getPurchasedCartsPaginated(IProfileUser $user, $page = 1, $limit = null);
}
