<?php

namespace Larapress\ECommerce\Commands;

use Illuminate\Console\Command;
use Larapress\ECommerce\Models\ProductType;

use function PHPUnit\Framework\directoryExists;

class ImportProductTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lp:ecommerce:import-types {path?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import product types from json.';

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
            $filepath = storage_path('/json/product_types.json');
        }

        $types = json_decode(file_get_contents($filepath), true);

        foreach ($types as $type) {
            ProductType::withTrashed()
                ->updateOrCreate([
                    'id' => $type['id'],
                    'name' => $type['name'],
                ], [
                    'data' => $type['data'],
                    'author_id' => $type['author_id'],
                    'flags' => $type['flags'],
                    'created_at' => $type['created_at'],
                    'updated_at' => $type['updated_at'],
                    'deleted_at' => $type['deleted_at'],
                ]);
            $this->info('Type added with name: ' . $type['name'] . '.');
        }

        $this->info('Product types imported.');

        return 0;
    }
}
