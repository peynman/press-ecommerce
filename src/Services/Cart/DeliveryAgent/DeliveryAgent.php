<?php

namespace Larapress\ECommerce\Services\Cart\DeliveryAgent;

use Larapress\ECommerce\Services\Cart\ICart;
use Larapress\ECommerce\Services\Cart\DeliveryAgent\IDeliveryAgentClient;

class DeliveryAgent implements IDeliveryAgent
{
    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @return IDeliveryAgentClient[]
     */
    public function getAvailableAgentsForCart(ICart $cart)
    {
        $avAgents = [];

        $address = $cart->getDeliveryAddress();
        if (is_null($address)) {
            return [];
        }

        $agents = config('larapress.ecommerce.delivery_agents');

        foreach ($agents as $agentName => $class) {
            /** @var IDeliveryAgentClient */
            $agent = new $class();
            if ($agent->canDeliveryForAddress($cart, $address)) {
                $avAgents[] = $agentName;
            }
        }

        return $avAgents;
    }
}
