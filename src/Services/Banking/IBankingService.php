<?php


namespace Larapress\ECommerce\Services\Banking;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\Profiles\Models\Domain;

interface IBankingService
{
    /**
     * @param Request            $request
     * @param Cart|integer       $cart
     * @param integer            $gateway_id
     * @param callable|null      $onFailed
     *
     * @return Response
     */
    public function redirectToBankForCart(Request $request, $cart, $gateway_id, $onFailed, $onAlreadyPurchased);

    /**
     * @param Request         $request
     * @param BankGatewayTransaction|integer         $transaction_id
     * @param callable        $onAlreadyPurchased
     * @param callable        $onSuccess
     * @param callable        $onFailed
     *
     * @return Response
     */
    public function verifyBankRequest(Request $request, $transaction, $onAlreadyPurchased, $onSuccess, $onFailed, $onCancel);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $gateway_id
     * @param float $amount
     * @param int $currency
     * @param callback|null $onFailed
     * @return Response
     */
    public function redirectToBankForAmount(Request $request, $gateway_id, $amount, $currency, $onFailed, $onAlreadyPurchased);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $cart_id
     * @return Response
     */
    public function updatePurchasingCart(Request $request, IECommerceUser $user, int $currency, $cart_id = null);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param Cart $cart
     * @return Cart
     */
    public function markCartPurchased($request, Cart $cart, $walletTimestamp = null);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param float $amount
     * @param integer $currency
     * @param integer $type
     * @param integer $flags
     * @param string $desc
     * @return [Cart, WalletTransaction]
     */
    public function addBalanceForUser(IECommerceUser $user, float $amount, int $currency, int $type, int $flags, string $desc);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param Domain $domain
     * @param array $ids
     * @param int $currency
     * @return Cart
     */
    public function createCartWithProductIDs(Request $request, IECommerceUser $user, array $ids, $currency);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param integer $currency
     * @param string $code
     * @return void
     */
    public function checkGiftCodeForPurchasingCart(Request $request, IECommerceUser $user, int $currency, string $code);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param ICartItem $cartItem
     * @return Cart
     */
    public function addItemToPurchasingCart(Request $request, IECommerceUser $user, ICartItem  $cartItem);


    /**
     * Undocumented function
     *
     * @param Request $request
     * @param ICartItem $cartItem
     * @return Cart
     */
    public function removeItemFromPurchasingCart(Request $request, IECommerceUser $user, ICartItem  $cartItem);


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer $currency
     * @return Cart
     */
    public function getPurchasingCart(IECommerceUser $user, int $currency);

    /**
     * Undocumented function
     *
     * @param int|Cart $originalCart
     * @param int|Product $product
     * @return Cart
     */
    public function getInstallmentsForProductInCart(IECommerceUser $user, $originalCart, $product);


    /**
     *
     */
    public function getInstallmentsForCartPeriodicCustom(IECommerceUser $user, $originalCart);

    /**
     * Undocumented function
     *
     * @return Cart[]
     */
    public function getInstallmentsForPeriodicPurchases();

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param Domain $domain
     * @param integer $currency
     * @return ICartItem[]
     */
    public function getPurchasingCartItems(IECommerceUser $user, int $currency);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return array
     */
    public function getPeriodicInstallmentsLockedProducts(IECommerceUser $user);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param Domain $domain
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
     * @param Domain $domain
     * @param integer $currency
     * @return float
     */
    public function getUserTotalAquiredGiftBalance(IECommerceUser $user, int $currency);

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return array
     */
    public function getPurchasedItemIds(IECommerceUser $user);


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return array
     */
    public function getPurchasedCarts(IECommerceUser $user);



    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer|Product $productId
     * @return boolean
     */
    public function isProductOnPurchasedList(IECommerceUser $user, $product);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $giftCodeId
     * @return mixed
     */
    public function duplicateGiftCodeForRequest(Request $request, $giftCodeId);
}
