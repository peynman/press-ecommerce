<?php

namespace Larapress\ECommerce\Commands;

use Illuminate\Console\Command;

use function PHPUnit\Framework\directoryExists;

class CreateLocalStorageFolders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lp:ecommerce:storage-folders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create local storage folders.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dirs = [
            storage_path('app/images'),
            storage_path('app/pdf'),
            storage_path('app/plupload'),
            storage_path('app/videos'),
            storage_path('app/temp'),
            storage_path('app/zip'),
            storage_path('app/public/images'),
            storage_path('app/public/pdf'),
            storage_path('app/public/videos'),
            storage_path('app/public/zip'),
        ];

        foreach ($dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir);
            }
        }

        $this->info('All local storage directories created.');

        return 0;
    }
}
