<?php

namespace Larapress\Ecommerce\Services\VOD;

use Illuminate\Http\Request;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\Ecommerce\Services\FileUpload\IFileUploadProcessor;
use Larapress\Reports\Models\TaskReport;
use Larapress\Reports\Services\ITaskReportService;
use Larapress\Reports\Services\ITaskHandler;

class VideoFileProcessor implements IFileUploadProcessor, ITaskHandler {
    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return FileUpload
     */
    public function postProcessFile(Request $request, FileUpload $upload) {
        /** @var ITaskReportService */
        $taskService = app(ITaskReportService::class);
        $taskService->scheduleTask(self::class, 'vod-convert', 'VOD Convert', [], $request->get('auto_start', false));
    }

    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return boolean
     */
    public function shouldProcessFile(FileUpload $upload) {
        return $upload->mime === 'application/video';
    }


    /**
     * Undocumented function
     *
     * @param TaskReport $task
     * @return void
     */
    public function handle(TaskReport $task) {
        /** @var ITaskReportService */
        $taskService = app(ITaskReportService::class);

    }
}
