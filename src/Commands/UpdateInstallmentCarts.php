<?php

namespace Larapress\ECommerce\Commands;

use Illuminate\Console\Command;

class UpdateInstallmentCarts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lp:ecommerce:update-installments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update Installments.';

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
        return 0;
    }
}
