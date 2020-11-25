<?php

namespace Larapress\ECommerce\Services\VOD;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Larapress\ECommerce\Models\FileUpload;
use Larapress\Reports\Services\ITaskReportService;

use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use FFMpeg\Format\Video\X264;
use FFMpeg\Coordinate\Dimension;
use Illuminate\Support\Facades\Storage;

class VideoConvertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * @var FileUpload
     */
    private $upload;

    /**
     * Create a new job instance.
     *
     * @param FileUpload $message
     */
    public function __construct(FileUpload $upload)
    {
        $this->upload = $upload;
        $this->onQueue(config('larapress.ecommerce.vod.queue'));
    }

    public function tags()
    {
        return ['video-convert', 'video:' . $this->upload->id];
    }
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        /** @var ITaskReportService */
        $taskService = app(ITaskReportService::class);

        $taskData = ['id' => $this->upload->id];
        $taskService->startSyncronizedTaskReport(
            VideoFileProcessor::class,
            'convert-' . $this->upload->id,
            'Started...',
            $taskData,
            function ($onUpdate, $onSuccess, $onFailed) use($taskData) {
                try {
                    $startTime = time();
                    $rates = config('larapress.ecommerce.vod.hls_variants');

                    /** @var HLSPlaylistExporter $mediaHLSExport */
                    $mediaHLSExport = FFMpeg::fromDisk($this->upload->storage)
                        ->open($this->upload->path)
                        ->exportForHLS();
                    foreach ($rates as $rate => $size) {
                        $bitRate = (new X264())
                            ->setKiloBitrate($rate)
                            ->setAudioCodec('libfdk_aac');

                        $mediaHLSExport->addFormat($bitRate, function ($media) use ($size) {
                            $media->addLegacyFilter(function ($filters) use ($size) {
                                $filters->resize(new Dimension($size[0], $size[1]));
                            });
                        });
                    }
                    $mediaHLSExport->onProgress(function ($percent) use($onUpdate, $taskData) {
                       $onUpdate('Converting %'.$percent.'...', $taskData);
                    });
                    $mediaHLSExport->setSegmentLength(10);

                    $dir = substr($this->upload->path, 0, strrpos($this->upload->path, '.', -1));
                    Storage::disk($this->upload->storage)->makeDirectory($dir);
                    $mediaHLSExport->save($dir . '/vod.m3u8');
                    $took = time() - $startTime;
                    $onSuccess('Finished, took '.$took.' sec.', $taskData);
                } catch (\Exception $e) {
                    $onFailed('Error: '.$e->getMessage(), $taskData);
                }
            }
        );
    }
}
