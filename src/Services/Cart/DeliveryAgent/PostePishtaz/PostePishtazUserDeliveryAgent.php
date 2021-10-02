<?php

namespace Larapress\ECommerce\Services\Cart\DeliveryAgent\PostePishtaz;

use Larapress\ECommerce\Services\Cart\DeliveryAgent\IDeliveryAgentClient;
use Larapress\ECommerce\Services\Cart\ICart;
use Larapress\Profiles\Models\PhysicalAddress;

class PostePishtazUserDeliveryAgent implements IDeliveryAgentClient
{
    /**
     * Undocumented function
     *
     * @return string
     */
    public function getAgentName()
    {
    }

    /**
     * Undocumented function
     *
     * @param ICart $cart
     *
     * @return int
     */
    public function getEstimatedDuration(PhysicalAddress $address)
    {
    }

    /**
     * Undocumented function
     *
     * @param ICart $cart
     *
     * @return float
     */
    public function getEstimatedPrice(PhysicalAddress $address, int $currency)
    {
        return 25000;
    }

    /**
     * Undocumented function
     *
     * @param ICart $cart
     *
     * @return mixed
     */
    public function getLetterStatus(ICart $cart)
    {
    }

    /**
     * Undocumented function
     *
     * @param PhysicalAddress $address
     * @return boolean
     */
    public function canDeliveryForAddress(PhysicalAddress $address)
    {
        return true;
    }
}
