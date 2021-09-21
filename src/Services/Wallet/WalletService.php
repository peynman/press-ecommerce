<?php

namespace Larapress\ECommerce\Services\Wallet;

use Carbon\Carbon;
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
     * @param array $data
     *
     * @return WalletTransaction
     */
    public function addBalanceForUser(IECommerceUser $user, float $amount, int $currency, int $type, int $flags, string $desc, array $data)
    {
        $wallet = WalletTransaction::create([
            'user_id' => $user->id,
            'domain_id' => $user->getMembershipDomainId(),
            'amount' => $amount,
            'currency' => $currency,
            'type' => $type,
            'flags' => $flags,
            'data' => array_merge([
                'description' => $desc,
                'balance' => $this->getUserBalance($user, $currency),
            ], $data)
        ]);

        $this->resetBalanceCache($user->id);
        WalletTransactionEvent::dispatch($wallet, Carbon::now());

        return $wallet;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     * @return float
     */
    public function getUserBalance(IECommerceUser $user, int $currency)
    {
        return floatval(Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.user-balance',
            ['user.wallet:' . $user->id],
            3600,
            true,
            function () use ($user, $currency) {
                return WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('type', '!=', WalletTransaction::TYPE_UNVERIFIED)
                    ->sum('amount');
            }
        ));
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
        return floatval(Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.virtual-balance',
            ['user.wallet:' . $user->id],
            3600,
            true,
            function () use ($user, $currency) {
                return WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('type', WalletTransaction::TYPE_VIRTUAL_MONEY)
                    ->sum('amount');
            }
        ));
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
        return floatval(Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.gift-balance',
            ['user.wallet:' . $user->id],
            3600,
            true,
            function () use ($user, $currency) {
                return WalletTransaction::query()
                    ->where('user_id', $user->id)
                    ->where('currency', $currency)
                    ->where('flags', '&', WalletTransaction::FLAGS_REGISTRATION_GIFT)
                    ->sum('amount');
            }
        ));
    }


    /**
     * @param int $userId
     * @return void
     */
    public function resetBalanceCache($userId)
    {
        Helpers::forgetCachedValues(['user.wallet:' . $userId]);
    }
}
