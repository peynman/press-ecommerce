<?php

namespace Larapress\ECommerce\Commands;

use Illuminate\Console\Command;
use Larapress\ECommerce\Models\ProductType;

use function PHPUnit\Framework\directoryExists;

class ExportProductTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lp:ecommerce:export-types {path?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export product types.';

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
        $filepath = $this->argument('path');
        if (is_null($filepath)) {
            if (!directoryExists(storage_path('json'))) {
                mkdir(storage_path('json'));
            }
            $filepath = storage_path('/json/product_types.json');
        }

        file_put_contents($filepath, json_encode(ProductType::all(), JSON_PRETTY_PRINT));
        $this->info('Product types exported to path: '.$filepath.'.');

        return 0;
    }
}
