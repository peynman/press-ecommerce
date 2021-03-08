<?php

namespace Larapress\ECommerce\Services\SupportGroup;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Profiles\Models\Form;
use Larapress\Profiles\Services\FormEntry\FormEntryUpdateEvent;

class SupportGroupFormListener implements ShouldQueue
{
    use Dispatchable;

    public function handle(FormEntryUpdateEvent $event)
    {
        switch ($event->formId) {
                // user profile updated
            case config('larapress.ecommerce.lms.profile_form_id'):
                if ($event->created) {
                    if (!is_null(config('larapress.ecommerce.lms.profle_gift.amount')) &&
                        !is_null(config('larapress.ecommerce.lms.profle_gift.currency')) &&
                        config('larapress.ecommerce.lms.profle_gift.amount') > 0
                    ) {
                        // add profile completion gift
                        /** @var IBankingService */
                        $bankingService = app(IBankingService::class);
                        $request = new Request();
                        $request->server->add(['REMOTE_ADDR' => $event->ip]);
                        $bankingService->addBalanceForUser(
                            $event->getUser(),
                            config('larapress.ecommerce.lms.profle_gift.amount'),
                            config('larapress.ecommerce.lms.profle_gift.currency'),
                            WalletTransaction::TYPE_VIRTUAL_MONEY,
                            WalletTransaction::FLAGS_REGISTRATION_GIFT,
                            trans('larapress::ecommerce.banking.messages.wallet-descriptions.profile_gift_wallet_desc')
                        );
                    }
                }
                break;
        }
    }
}
