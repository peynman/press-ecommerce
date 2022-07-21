<?php

namespace Larapress\ECommerce\Services\Cart\DeliveryAgent;

use Larapress\ECommerce\Services\Cart\ICart;
use Larapress\Profiles\Models\PhysicalAddress;

interface IDeliveryAgentClient
{
    /**
     * Undocumented function
     *
     * @return string
     */
    public function getAgentName();

    /**
     * Undocumented function
     *
     * @param ICart $cart
     *
     * @return int
     */
    public function getEstimatedDuration(ICart $cart, PhysicalAddress $address);

    /**
     * Undocumented function
     *
     * @param ICart $cart
     *
     * @return float
     */
    public function getEstimatedPrice(ICart $cart, PhysicalAddress $address, int $currency);

    /**
     * Undocumented function
     *
     * @param ICart $cart
     *
     * @return mixed
     */
    public function getLetterStatus(ICart $cart);

    /**
     * Undocumented function
     *
     * @param PhysicalAddress $address
     * @return boolean
     */
    public function canDeliveryForAddress(ICart $cart, PhysicalAddress $address);
}
