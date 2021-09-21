<?php

namespace Larapress\ECommerce\Services\Banking;

use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\ECommerce\Models\BankGatewayTransaction;

class BankGatewayTransactionEvent implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    /** @var string */
    public $ip;
    /** @var int */
    public $timestamp;
    /** @var BankGatewayTransaction */
    public $transactionId;

    /**
     * Create a new event instance.
     *
     * @param $user
     * @param $domain
     * @param $ip
     * @param $timestamp
     */
    public function __construct(BankGatewayTransaction $transaction, $ip, Carbon $timestamp)
    {
        $this->transactionId = $transaction->id;
        $this->ip = $ip;
        $this->timestamp = $timestamp->getTimestamp();
    }

    /**
     * Undocumented function
     *
     * @return BankGatewayTransaction
     */
    public function getGatewayTransaction()
    {
        return BankGatewayTransaction::find($this->transactionId);
    }
}
