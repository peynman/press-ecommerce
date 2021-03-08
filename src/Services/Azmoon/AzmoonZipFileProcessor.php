<?php

namespace Larapress\ECommerce\Services\Azmoon;

use Illuminate\Http\Request;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\ECommerce\Services\FileUpload\IFileUploadProcessor;
use Larapress\Reports\Models\TaskReport;
use Larapress\Reports\Services\ITaskHandler;

class AzmoonZipFileProcessor implements IFileUploadProcessor, ITaskHandler
{
    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return FileUpload
     */
    public function postProcessFile(Request $request, FileUpload $upload)
    {
        AzmoonExtractJob::dispatch($upload);
    }

    /**
     * Undocumented function
     *
     * @param FileUpload $upload
     * @return boolean
     */
    public function shouldProcessFile(FileUpload $upload)
    {
        return \Illuminate\Support\Str::startsWith($upload->mime, 'application/zip');
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
        AzmoonExtractJob::dispatch($upload);
    }
}
