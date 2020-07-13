<?php

namespace Larapress\ECommerce\Services\Banking\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Models\Cart;

class CartPurchasedEvent implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    /** @var \Larapress\Profiles\Models\Domain */
    public $domain;
    /** @var string */
    public $ip;
    /** @var int */
    public $timestamp;
    /** @var Cart */
    public $cart;

    /**
     * Create a new event instance.
     *
     * @param $user
     * @param $domain
     * @param $ip
     * @param $timestamp
     */
    public function __construct($domain, $ip, $timestamp, Cart $cart)
    {
        $this->domain = $domain;
        $this->ip = $ip;
        $this->timestamp = $timestamp;
        $this->cart = $cart;
    }
}
