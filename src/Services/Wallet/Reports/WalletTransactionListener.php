<?php

namespace Larapress\ECommerce\Services\Wallet\Reports;

use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Wallet\WalletTransactionEvent;
use Larapress\Reports\Services\Reports\IMetricsService;

class WalletTransactionListener implements ShouldQueue
{
    public function __construct(public IMetricsService $metrics)
    {
    }

    public function handle(WalletTransactionEvent $event)
    {
        /** @var WalletTransaction */
        $transaction = $event->getWalletTransaction();

        if (config('larapress.ecommerce.reports.wallet_transactions')) {
            $this->metrics->updateMeasurement(
                $transaction->domain_id,
                $transaction->user_id,
                null,
                $transaction->user->getMembershipGroupIds(),
                config('larapress.ecommerce.reports.wallet_transactions'),
                config('larapress.ecommerce.reports.group'),
                'wallet_transaction.'.$transaction->id,
                $transaction->amount,
                [
                    'type' => $transaction->type,
                ],
                $event->timestamp
            );
        }
    }
}
