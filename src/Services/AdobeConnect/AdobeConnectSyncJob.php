<?php

namespace Larapress\ECommerce\Services\AdobeConnect;

use Larapress\Notifications\Models\SMSMessage;
use Larapress\Notifications\SMSService\ISMSService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Larapress\CRUD\ServicesFlags;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\ECommerce\Models\Product;
use Larapress\Notifications\CRUD\SMSMessageCRUDProvider;
use Larapress\Notifications\Models\SMSGatewayData;

class AdobeConnectSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
	/**
	 * @var Product
	 */
	private $product;

	/**
	 * Create a new job instance.
	 *
	 * @param Product $message
	 */
    public function __construct(Product $product)
    {
	    $this->product = $product;
	    $this->onQueue(config('larapress.crud.queue'));
    }

    public function tags()
    {
        return ['adobe-connect', 'product:'.$this->product->id];
    }
	/**
	 * Execute the job.
	 *
	 * @param ISMSService $service
	 *
	 * @return void
	 */
    public function handle(ISMSService $service)
    {
    }
}
