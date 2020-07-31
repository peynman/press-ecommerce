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
    protected $description = 'ask for event statuses from adobe connect';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct([
            'sync:lives' => $this->syncLiveEvents(),
        ]);
    }

    public function syncLiveEvents()
    {
        return function () {
            /** @var IAdobeConnectService */
            $service = app(IAdobeConnectService::class);

            $products =
                Product::with('types')
                ->whereHas('types', function ($q) {
                    $q->where('name', 'ac_meeting');
                })
                ->get();


            foreach ($products as $product) {
                if (
                    isset($product->data['types']['ac_meeting']['status']) &&
                    $product->data['types']['ac_meeting']['status'] !== 'ended'
                ) {
                    $service->onEachServerForProduct($product, function($meetingFolder, $meetingName)  use($service, $product) {
                        $meeting = $service->createOrGetMeeting($meetingFolder, $meetingName);
                    });
                }
            }
        };
    }
}
