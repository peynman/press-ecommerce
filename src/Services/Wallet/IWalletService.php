<?php

namespace Larapress\ECommerce\Services\Wallet;

use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\WalletTransaction;

interface IWalletService {

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     * @return float
     */
    public function getUserBalance(IECommerceUser $user, int $currency);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     * @return float
     */
    public function getUserVirtualBalance(IECommerceUser $user, int $currency);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     * @return float
     */
    public function getUserTotalAquiredGiftBalance(IECommerceUser $user, int $currency);

    /**
     * Undocumented function
     *
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
    public function addBalanceForUser(IECommerceUser $user, float $amount, int $currency, int $type, int $flags, string $desc, array $data);


    /**
     * @param int $userId
     * @return void
     */
    public function resetBalanceCache($userId);
}
