<?php

namespace Larapress\ECommerce\Services\SupportGroup;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Banking\IBankingService;
use Larapress\Profiles\Models\FormEntry;
use Larapress\Profiles\Services\FormEntry\FormEntryUpdateEvent;

class SupportGroupFormListener {
    public function handle(FormEntryUpdateEvent $event) {
        Log::debug('reset cache');
        switch ($event->form->id) {
            // user profile updated
            case config('larapress.profiles.defaults.profile-form-id'):
                if ($event->created) {
                    // add profile completion gift
                    /** @var IBankingService */
                    $bankingService = app(IBankingService::class);
                    $request = new Request();
                    $request->server->add(['REMOTE_ADDR' => $event->ip]);
                    $bankingService->addBalanceForUser(
                        $request,
                        $event->user,
                        config('larapress.ecommerce.lms.profle_gift.amount'),
                        config('larapress.ecommerce.lms.profle_gift.currency'),
                        WalletTransaction::TYPE_MANUAL_MODIFY,
                        WalletTransaction::FLAGS_REGISTRATION_GIFT,
                        trans('larapress::ecommerce.banking.messages.wallet-descriptions.profile_gift_wallet_desc')
                    );
                }
                // update internal fast cache!
                $event->user->updateUserCache('profile');
            break;
            // support profile updated
            case config('larapress.profiles.defaults.profile-support-form-id'):
                // update all users with this new support data
                FormEntry::query()->select('id', 'user_id')->with('user')
                ->where('tags', 'support-group-' . $event->user->id)
                ->chunk(100, function ($entries) use($event) {
                    foreach ($entries as $entry) {
                        $entry->user->updateUserCache('support');
                    }
                });

                // update internal fast cache!
                $event->user->updateUserCache('profile');
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
