<?php

namespace Larapress\ECommerce\Commands;

use Larapress\Reports\Models\MetricCounter;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Larapress\CRUD\Commands\ActionCommandBase;
use Larapress\CRUD\Events\CRUDVerbEvent;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\AdobeConnect\IAdobeConnectService;
use Larapress\Reports\CRUD\TaskReportsCRUDProvider;
use Larapress\Reports\Models\TaskReport;
use Larapress\Reports\Services\IMetricsService;
use Larapress\Reports\Services\IReportsService;
use Larapress\Reports\Services\ITaskReportService;

class AdobeConnect extends ActionCommandBase
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larapress:ac {--action=} {--product=}';

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
            'migrate' => $this->migrate(),
        ]);
    }

    public function migrate()
    {
        return function () {
            /** @var IAdobeConnectService */
            $service = app(IAdobeConnectService::class);

            $product = Product::with('types')->find($this->option('product'));
            if (is_null($product)) {
                throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
            }

            $types = $product->types;
            $isAC = false;
            foreach ($types as $type) {
                if ($type->name === 'ac_meeting') {
                    $isAC = true;
                }
            }
            if (!$isAC) {
                throw new AppException(AppException::ERR_INVALID_QUERY);
            }

            $service->connect(
                $product->data['types']['ac_meeting']['server'],
                $product->data['types']['ac_meeting']['username'],
                $product->data['types']['ac_meeting']['password']
            );
            $meetingName = isset($product->data['types']['ac_meeting']['meeting_name']) && !empty($product->data['types']['ac_meeting']['meeting_name']) ?
                $product->data['types']['ac_meeting']['meeting_name'] : 'ac-product-' . $product->id;
            $meetingFolder = isset($product->data['types']['ac_meeting']['meeting_folder']) && !empty($product->data['types']['ac_meeting']['meeting_folder']) ?
                $product->data['types']['ac_meeting']['meeting_folder'] : 'meetings';

            $isFree = $product->isFree();
            if (!$isFree && !is_null($product->parent_id)) {
                $parent = $product->parent;
                $isFree = $parent->isFree();
            }
            $participants = User::whereHas('carts', function ($q) use ($product) {
                $q->whereHas('products', function ($q) use ($product) {
                    $q->where('id', $product->id);
                });
            });

            foreach($participants as $participant) {

            }
        };
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
                        $periodicPurchases = isset($cart->data['periodic_product_ids']) ? $cart->data['periodic_product_ids'] : [];
                        foreach ($items as $item) {
                            $periodic = in_array($item->id, $periodicPurchases);
                            $metrics->pushMeasurement(
                                $cart->domain_id,
                                'product.' . $item->id . '.sales_amount',
                                $periodic ? $item->pricePeriodic() : $item->price(),
                                $cart->updated_at,
                            );
                            if ($periodic) {
                                $metrics->pushMeasurement(
                                    $cart->domain_id,
                                    'product.' . $item->id . '.sales_periodic',
                                    1,
                                    $cart->updated_at,
                                );
                            } else {
                                $metrics->pushMeasurement(
                                    $cart->domain_id,
                                    'product.' . $item->id . '.sales_fixed',
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
}
