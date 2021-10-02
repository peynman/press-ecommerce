<?php

namespace Larapress\ECommerce\Services\Cart\DeliveryAgent;

use Larapress\ECommerce\Services\Cart\ICart;

interface IDeliveryAgent
{
    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @return IDeliveryAgentClient[]
     */
    public function getAvailableAgentsForCart(ICart $cart);
}
