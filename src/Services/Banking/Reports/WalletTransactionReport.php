<?php

namespace Larapress\ECommerce\Services\Banking\Reports;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Larapress\CRUD\Services\IReportSource;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\Events\WalletTransactionEvent;
use Larapress\Reports\Services\BaseReportSource;
use Larapress\Reports\Services\IMetricsService;
use Larapress\Reports\Services\IReportsService;

class WalletTransactionReport implements IReportSource, ShouldQueue
{
    use BaseReportSource;

    /** @var IReportsService */
    private $reports;

    /** @var IMetricsService */
    private $metrics;

    /** @var array */
    private $avReports;

    public function __construct(IReportsService $reports, IMetricsService $metrics)
    {
        $this->reports = $reports;
        $this->metrics = $metrics;
        $this->avReports = [
            'user.wallet.total' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                return $this->reports->queryMeasurement(
                    'user_wallet',
                    $filters,
                    $groups,
                    array_merge(["_value"], $groups),
                    $fromC,
                    $toC,
                    'count()'
                );
            },
            'user.wallet.windowed' => function ($user, array $options = []) {
                [$filters, $fromC, $toC, $groups] = $this->getCommonReportProps($user, $options);
                $window = isset($options['window']) ? $options['window'] : '1h';
                return $this->reports->queryMeasurement(
                    'user_wallet',
                    $filters,
                    $groups,
                    array_merge(["_value", "_time"], $groups),
                    $fromC,
                    $toC,
                    'aggregateWindow(every: '.$window.', fn: sum)'
                );
            }
        ];
    }

    public function handle(WalletTransactionEvent $event)
    {
        /** @var WalletTransaction */
        $transaction = WalletTransaction::with([
            'user',
        ])->find($event->transactionId);

        $supportProfileId = $transaction->user->getSupportUserId();
        $supportProfileTimestamp = $transaction->user->getSupportUserStartedDate();
        $supportRole = $transaction->user->getSupportUserRole();

        $tags = [
            'domain' => $transaction->domain_id,
            'currency' => $transaction->currency,
            'type' => $transaction->type,
            'decrease' => floatval($transaction->amount) > 0,
            'tr_id' => $transaction->id,
            'support' => $supportProfileId,
        ];
        $this->reports->pushMeasurement('user_wallet', 1, $tags, [
            'amount' => floatval($transaction->amount),
        ], $event->timestamp);

        // in case of metrics we use $transactions created_at as timestamp
        //   on WalletTransaction updates we dont want to change the original records timestamp of measurement
        $purchaseTimestamp = isset($transaction->data['period_start']) ? Carbon::parse($transaction->data['period_start']) : $transaction->created_at;

        $tags = isset($transaction->data['cart_id']) ? 'cart:'.$transaction->data['cart_id'] : 'wallet:'.$transaction->id;

        $this->metrics->pushMeasurement(
            $transaction->domain_id,
            $tags,
            'wallet.'.$transaction->type.'.amount',
            $transaction->amount,
            $purchaseTimestamp
        );

        if (!is_null($supportProfileId) && $supportProfileTimestamp <= $purchaseTimestamp) {
            $this->metrics->pushMeasurement(
                $transaction->domain_id,
                $tags,
                'wallet.'.$transaction->type.'.amount.'.$supportProfileId,
                $transaction->amount,
                $purchaseTimestamp
            );
        }

        // add each products share for this wallet transaction
        if (isset($transaction->data['product_shares'])) {
            $shares = $transaction->data['product_shares'];
            foreach ($shares as $productId => $shareAmount) {
                $this->metrics->pushMeasurement(
                    $transaction->domain_id,
                    $tags,
                    'product.'.$productId.'.sales.'.$transaction->type.'.amount',
                    floatval($shareAmount),
                    $purchaseTimestamp
                );
            }

            if (!is_null($supportProfileId) && $supportProfileTimestamp <= $purchaseTimestamp) {
                foreach ($shares as $productId => $shareAmount) {
                    $this->metrics->pushMeasurement(
                        $transaction->domain_id,
                        $tags,
                        'product.'.$productId.'.sales.'.$transaction->type.'.amount.'.$supportProfileId,
                        floatval($shareAmount),
                        $purchaseTimestamp
                    );
                    $this->metrics->pushMeasurement(
                        $transaction->domain_id,
                        $tags,
                        'product.'.$productId.'.sales.'.$transaction->type.'.roles.'.$supportRole->name.'.amount',
                        floatval($shareAmount),
                        $purchaseTimestamp
                    );
                }
            }
        }
    }
}
