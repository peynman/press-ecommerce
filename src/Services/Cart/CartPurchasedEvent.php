<?php

namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\ECommerce\Models\Cart;

class CartPurchasedEvent implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    /** @var int */
    public $timestamp;
    /** @var int */
    public $cartId;

    /**
     * Create a new event instance.
     *
     * @param Cart|int $cart
     * @param Carbon|int $timestamp
     */
    public function __construct($cart, Carbon $timestamp)
    {
        $this->cartId = is_numeric($cart) ? $cart : $cart->id;
        $this->timestamp = $timestamp->getTimestamp();
    }

    /**
     * Undocumented function
     *
     * @return Cart
     */
    public function getCart() {
        return Cart::find($this->cartId);
    }

    /**
     * Undocumented function
     *
     * @return Carbon
     */
    public function getTimestamp() {
        return Carbon::createFromTimestampUTC($this->timestamp);
    }
}
