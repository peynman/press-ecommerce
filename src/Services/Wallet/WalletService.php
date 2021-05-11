<?php

namespace Larapress\ECommerce\Services\Wallet;

use Illuminate\Support\Facades\Cache;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\WalletTransaction;

class WalletService implements IWalletService
{
    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param float $amount
     * @param integer $currency
     * @param integer $type
     * @param integer $flags
     * @param string $desc
     * @return WalletTransaction
     */
    public function addBalanceForUser(IECommerceUser $user, float $amount, int $currency, int $type, int $flags, string $desc)
    {
        $supportProfileId = isset($user->supportProfile['id']) ? $user->supportProfile['id'] : null;
        $wallet = WalletTransaction::create([
            'user_id' => $user->id,
            'domain_id' => $user->getMembershipDomainId(),
            'amount' => $amount,
            'currency' => $currency,
            'type' => $type,
            'flags' => $flags,
            'data' => [
                'description' => $desc,
                'balance' => $this->getUserBalance($user, $currency),
                'support' => $supportProfileId,
            ]
        ]);

        $this->resetBalanceCache($user->id);
        WalletTransactionEvent::dispatch($wallet, time());

        return $wallet;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     * @return void
     */
    public function getUserBalance(IECommerceUser $user, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.user-balance',
            function () use ($user, $currency) {
                return WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('type', '!=', WalletTransaction::TYPE_UNVERIFIED)
                    ->sum('amount');
            },
            ['user.wallet:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     * @return float
     */
    public function getUserVirtualBalance(IECommerceUser $user, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.virtual-balance',
            function () use ($user, $currency) {
                return WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('type', WalletTransaction::TYPE_VIRTUAL_MONEY)
                    ->sum('amount');
            },
            ['user.wallet:' . $user->id],
            null
        );
    }


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     * @return float
     */
    public function getUserTotalAquiredGiftBalance(IECommerceUser $user, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.gift-balance',
            function () use ($user, $currency) {
                return WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('flags', '&', WalletTransaction::FLAGS_REGISTRATION_GIFT)
                    ->sum('amount');
            },
            ['user.wallet:' . $user->id],
            null
        );
    }


    /**
     * @param int $userId
     * @return void
     */
    protected function resetBalanceCache($userId)
    {
        Cache::tags(['user.wallet:' . $userId])->flush();
    }
}
