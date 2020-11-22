<?php

namespace Larapress\ECommerce\Services\Banking\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\ECommerce\Models\BankGatewayTransaction;

class BankGatewayTransactionEvent implements ShouldQueue
{
    use Dispatchable, SerializesModels;

    /** @var \Larapress\Profiles\Models\Domain */
    public $domainId;
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
    public function __construct($domain, $ip, $timestamp, BankGatewayTransaction $transaction)
    {
        $this->transactionId = $transaction->id;
        $this->domainId = is_numeric($domain) ? $domain : $domain->id;
        $this->ip = $ip;
        $this->timestamp = $timestamp;
    }

    public function getGatewayTransaction() {
        return BankGatewayTransaction::find($this->transactionId);
    }
}
