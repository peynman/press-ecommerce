<?php

namespace Larapress\ECommerce\Services\SupportGroup;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Larapress\Auth\Signup\SignupEvent;
use Larapress\Profiles\Services\FormEntry\FormEntryUpdateEvent;

class SupportGroupSignupListener implements ShouldQueue {
    use Dispatchable;

    public function handle(SignupEvent $event) {
        ini_set('max_execution_time', 0);

        /** @var ISupportGroupService */
        $supportService = app(ISupportGroupService::class);
        // add user to support/introducer group, if we have introducer
        // add user gift balance too
        $supportService->updateUserRegistrationGiftWithIntroducer($event->getUser(), $event->getIntroducer(), true, true);
    }
}
