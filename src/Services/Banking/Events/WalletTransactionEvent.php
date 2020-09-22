<?php

namespace Larapress\ECommerce\Services\Banking\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\ECommerce\Models\WalletTransaction;

class WalletTransactionEvent implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    /** @var int */
    public $timestamp;
    /** @var WalletTransaction */
    public $transaction;

    /**
     * Create a new event instance.
     *
     * @param $user
     * @param $domain
     * @param $ip
     * @param $timestamp
     */
    public function __construct(WalletTransaction $transaction, $timestamp)
    {
        $this->timestamp = $timestamp;
        $this->transaction = $transaction;
    }
}
