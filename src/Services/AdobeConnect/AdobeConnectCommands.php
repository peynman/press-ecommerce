<?php

namespace Larapress\ECommerce\Services\AdobeConnect;

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
use Larapress\ECommerce\Services\CourseSession\ICourseSessionFormService;
use Larapress\Reports\CRUD\TaskReportsCRUDProvider;
use Larapress\Reports\Models\TaskReport;
use Larapress\Reports\Services\IMetricsService;
use Larapress\Reports\Services\IReportsService;
use Larapress\Reports\Services\ITaskReportService;

class AdobeConnectCommands extends ActionCommandBase
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
     * Cr eate a new command instance.
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
            ini_set('memory_limit', '1G');
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
                    $fullyEnded = true;
                    $recordings = [];
                    $service->onEachServerForProduct($product, function($meetingFolder, $meetingName, $serverData)  use($service, $product, &$fullyEnded, &$recordings) {
                        $meeting = $service->createOrGetMeeting($meetingFolder, $meetingName);
                        $attendances = $service->getMeetingAttendance($meeting->getScoId());
                        $serverEnded = true;
                        foreach($attendances as $attendance) {
                            if (!isset($attendance['dateEnd'])) {
                                $fullyEnded = false;
                                $serverEnded = false;
                            }
                        }

                        if ($serverEnded) {
                            /** @var SCO[] */
                            $records = $service->getMeetingRecordings($meeting->getScoId());
                            foreach ($records as $record) {
                                $recordings[] = trim($serverData['server'], '/').$record->getUrlPath();
                            }
                        }
                    });

                    if ($fullyEnded) {
                        $data = $product->data;
                        $data['types']['ac_meeting']['status'] = 'ended';
                        $data['types']['ac_meeting']['recordings'] = $recordings;

                        $product->update([
                            'data' => $data
                        ]);

                        /** @var ICourseSessionFormService */
                        $courseService = app(ICourseSessionFormService::class);
                        $service->onEachServerForProduct($product, function($meetingFolder, $meetingName)  use($service, $product, $courseService) {
                            $meeting = $service->createOrGetMeeting($meetingFolder, $meetingName);
                            $attendances = $service->getMeetingAttendance($meeting->getScoId());
                            foreach($attendances as $attendance) {
                                if (!isset($attendance['login'])) {
                                    continue;
                                }

                                $username = $attendance['login'];
                                $user = $service->getUserFromACLogin($username);
                                if (!is_null($user)) {
                                    $end = Carbon::createFromFormat('Y-m-d\TH:i:s.vO', $attendance['dateEnd']);
                                    $start = Carbon::createFromFormat('Y-m-d\TH:i:s.vO', $attendance['dateCreated']);
                                    $duration = $start->diffInSeconds($end);
                                    $courseService->addCourseSessionPresenceMarkForSession(
                                        null,
                                        $user,
                                        $product->id,
                                        1,
                                        $start
                                    );
                                    $courseService->addCourseSessionPresenceMarkForSession(
                                        null,
                                        $user,
                                        $product->id,
                                        $duration,
                                        $end
                                    );
                                }
                            }
                        });

                        $this->info("Product with id ".$product->id." is ac_meeting and ended");
                    }
                }
            }
        };
    }
}
