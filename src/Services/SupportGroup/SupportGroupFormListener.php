<?php

namespace Larapress\ECommerce\Services\SupportGroup;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Profiles\Models\FormEntry;
use Larapress\Profiles\Services\FormEntry\FormEntryUpdateEvent;

class SupportGroupFormListener implements ShouldQueue {
    use Dispatchable;

    public function handle(FormEntryUpdateEvent $event) {
        ini_set('max_execution_time', 0);
        switch ($event->form->id) {
            // user profile updated
            case config('larapress.profiles.defaults.profile-form-id'):
                if ($event->created) {
                    if (!is_null(config('larapress.ecommerce.lms.profle_gift.amount')) || !is_null(config('larapress.ecommerce.lms.profle_gift.currency')) &&
                        config('larapress.ecommerce.lms.profle_gift.currency') && config('larapress.ecommerce.lms.profle_gift.amount')) {
                        // add profile completion gift
                        /** @var IBankingService */
                        $bankingService = app(IBankingService::class);
                        $request = new Request();
                        $request->server->add(['REMOTE_ADDR' => $event->ip]);
                        $bankingService->addBalanceForUser(
                            $event->user,
                            config('larapress.ecommerce.lms.profle_gift.amount'),
                            config('larapress.ecommerce.lms.profle_gift.currency'),
                            WalletTransaction::TYPE_VIRTUAL_MONEY,
                            WalletTransaction::FLAGS_REGISTRATION_GIFT,
                            trans('larapress::ecommerce.banking.messages.wallet-descriptions.profile_gift_wallet_desc')
                        );
                    }
                }

                // update internal fast cache!
                $event->user->updateUserCache('profile');
            break;
            // support profile updated
            case config('larapress.profiles.defaults.profile-support-form-id'):
                // update internal fast cache!
                $event->user->updateUserCache('profile');

                // update all users with this new support data
                FormEntry::query()->select('id', 'user_id')->with('user')
                ->where('tags', 'support-group-' . $event->user->id)
                ->chunk(10, function ($entries) use($event) {
                    foreach ($entries as $entry) {
                        $entry->user->updateUserCache('support');
                    }
                });
            break;
            // support group updated
            case config('larapress.profiles.defaults.support-registration-form-id'):
                // update internal fast cache!
                $event->user->updateUserCache('support');
            break;
            case config('larapress.ecommerce.lms.introducer_default_form_id'):

            break;
        }
    }
}
