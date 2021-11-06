<?php

namespace Larapress\ECommerce\Services\Cart\DeliveryAgent\AloPeyk;

use Larapress\ECommerce\Services\Cart\DeliveryAgent\IDeliveryAgentClient;
use Larapress\ECommerce\Services\Cart\ICart;
use Larapress\Profiles\Models\PhysicalAddress;

class AloPeykDeliveryAgent implements IDeliveryAgentClient {

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
        return 40000;
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
        return $address->province_code === 7 && in_array($address->city_code, [10, 11]);
    }
}
