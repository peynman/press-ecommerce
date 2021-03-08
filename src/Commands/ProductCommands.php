<?php

namespace Larapress\ECommerce\Commands;

use Larapress\Reports\Models\MetricCounter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Commands\ActionCommandBase;
use Larapress\CRUD\Events\CRUDVerbEvent;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Services\AdobeConnect\IAdobeConnectService;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Reports\CRUD\TaskReportsCRUDProvider;
use Larapress\Reports\Models\TaskReport;
use Larapress\Reports\Services\IMetricsService;
use Larapress\Reports\Services\IReportsService;
use Larapress\Reports\Services\ITaskReportService;

class ProductCommands extends ActionCommandBase
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larapress:ecommerce {--action=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'report events to influx db';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct([
            'sales:generate' => $this->salesGenerate(),
            'sales:reset' => $this->salesReset(),
            'carts:installments' => $this->cartsInstallments(),
        ]);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function salesGenerate()
    {
        return function () {
            /** @var IMetricsService */
            $metrics = app(IMetricsService::class);
            Cart::query()
            ->with('products')
            ->where('status', Cart::STATUS_ACCESS_COMPLETE)
            ->chunk(100, function ($carts) use ($metrics) {
                $this->info("Processing carts...");
                foreach ($carts as $cart) {
                    /** @var ICartItem[] */
                    $items = $cart->products;
                    $periodicPurchases = isset($cart->data['periodic_product_ids']) ? $cart->data['periodic_product_ids']:[];
                    foreach ($items as $item) {
                        $periodic = in_array($item->id, $periodicPurchases);
                        $metrics->pushMeasurement(
                            $cart->domain_id,
                            'product.'.$item->id.'.sales_amount',
                            $periodic ? $item->pricePeriodic() : $item->price(),
                            $cart->updated_at,
                        );
                        if ($periodic) {
                            $metrics->pushMeasurement(
                                $cart->domain_id,
                                'product.'.$item->id.'.sales_periodic',
                                1,
                                $cart->updated_at,
                            );
                        } else {
                            $metrics->pushMeasurement(
                                $cart->domain_id,
                                'product.'.$item->id.'.sales_fixed',
                                1,
                                $cart->updated_at,
                            );
                        }
                    }
                }
            });
        };
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function salesReset()
    {
        return function () {
            MetricCounter::where('key', 'LIKE', 'product.%.sales_%')->delete();
            $this->info("Flushed metric keys LIKE product.%.sales_%");
        };
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function cartsInstallments()
    {
        return function () {
            /** @var IBankingService */
            $service = app(IBankingService::class);
            $service->getInstallmentsForPeriodicPurchases();
        };
    }
}
