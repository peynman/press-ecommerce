<?php

namespace Larapress\ECommerce\Commands;

use Illuminate\Console\Command;
use Larapress\ECommerce\Models\BankGateway;

class ExportBankGateways extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lp:ecommerce:export-gateways {path?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export bank gateways.';

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
            if (!file_exists(storage_path('json'))) {
                mkdir(storage_path('json'));
            }
            $filepath = storage_path('/json/bank_gateways.json');
        }

        file_put_contents($filepath, json_encode(BankGateway::all(), JSON_PRETTY_PRINT));
        $this->info('Bank Gateways exported to path: '.$filepath.'.');

        return 0;
    }
}
