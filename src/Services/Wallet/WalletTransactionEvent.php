<?php

namespace Larapress\ECommerce\Services\Wallet;

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
    /** @var int */
    public $transactionId;

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
        $this->transactionId = is_numeric($transaction) ? $transaction : $transaction->id;
        $this->timestamp = $timestamp;
    }
}
