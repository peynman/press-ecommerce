<?php

namespace Larapress\ECommerce\Services\VOD;

use Illuminate\Http\Request;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\ECommerce\Services\FileUpload\IFileUploadProcessor;
use Larapress\Reports\Models\TaskReport;
use Larapress\Reports\Services\ITaskHandler;
use Larapress\Reports\Services\ITaskReportService;

class VideoFileProcessor implements IFileUploadProcessor, ITaskHandler
{
    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return FileUpload
     */
    public function postProcessFile(Request $request, FileUpload $upload)
    {
        /** @var ITaskReportService */
        $taskService = app(ITaskReportService::class);
        $autoStart = $request->get('auto_start', false);
        if ($autoStart) {
            $autoStart = $request->get('start_at', true);
        }
        $taskService->scheduleTask(self::class, 'convert-'.$upload->id, 'Queued Convert.', ['id' => $upload->id], $autoStart);
    }

    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return boolean
     */
    public function shouldProcessFile(FileUpload $upload)
    {
        return \Illuminate\Support\Str::startsWith($upload->mime, 'video/');
    }

    /**
     * Undocumented function
     *
     * @param TaskReport $task
     * @return void
     */
    public function handle(TaskReport $task)
    {
        $upload = FileUpload::find($task->data['id']);
        VideoConvertJob::dispatch($upload);
    }
}
