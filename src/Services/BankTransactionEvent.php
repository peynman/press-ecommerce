<?php

namespace Larapress\ECommerce\Services;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Larapress\ECommerce\Models\BankGatewayTransaction;

class BankTransactionEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var \Larapress\ECommerce\Models\BankGatewayTransaction */
    public $transaction;
    /** @var string */
    public $ip;

    /**
     * Create a new event instance.
     *
     * @param $user
     * @param $domain
     * @param $ip
     */
    public function __construct(BankGatewayTransaction $transaction, $ip)
    {
        $this->transaction = $transaction;
        $this->ip = $ip;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel(config('larapress.crud.events.channel'));
    }
}
