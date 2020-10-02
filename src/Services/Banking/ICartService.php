<?php


namespace Larapress\ECommerce\Services\Banking;


use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\BankGatewayTransaction;
use Larapress\Profiles\IProfileUser;
use Larapress\Profiles\Models\Domain;

interface ICartService
{
    /**
     * Undocumented function
     *
     * @param Request $request
     * @param int $cart_id
     * @return Response
     */
    public function updatePurchasingCart(Request $request, int $currency, $cart_id = null);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param Cart $cart
     * @return Cart
     */
    public function markCartPurchased(Request $request, Cart $cart);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IProfileUser $user
     * @param float $amount
     * @param integer $currency
     * @param integer $type
     * @param integer $flags
     * @param string $desc
     * @return [Cart, WalletTransaction]
     */
    public function addBalanceForUser(Request $request, IProfileUser $user, float $amount, int $currency, int $type, int $flags, string $desc);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IProfileUser $user
     * @param Domain $domain
     * @param array $ids
     * @param int $currency
     * @return Cart
     */
    public function createCartWithProductIDs(Request $request, IProfileUser $user, Domain $domain, array $ids, $currency);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param integer $currency
     * @param string $code
     * @return void
     */
    public function checkGiftCodeForPurchasingCart(Request $request, int $currency, string $code);

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param ICartItem $cartItem
     * @return Cart
     */
    public function addItemToPurchasingCart(Request $request, ICartItem  $cartItem);


    /**
     * Undocumented function
     *
     * @param Request $request
     * @param ICartItem $cartItem
     * @return Cart
     */
    public function removeItemFromPurchasingCart(Request $request, ICartItem  $cartItem);


    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @param integer $currency
     * @return Cart
     */
    public function getPurchasingCart(IProfileUser $user, int $currency);

    /**
     * Undocumented function
     *
     * @param int|Cart $originalCart
     * @param int|Product $product
     * @return Cart
     */
    public function getInstallmentsForProductInCart(IProfileUser $user, $originalCart, $product);

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
     * @param IProfileUser $user
     * @param Domain $domain
     * @param integer $currency
     * @return ICartItem[]
     */
    public function getPurchasingCartItems(IProfileUser $user, int $currency);

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @return array
     */
    public function getPeriodicInstallmentsLockedProducts(IProfileUser $user);

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @return array
     */
    public function getPurchasedItemIds(IProfileUser $user);

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @return array
     */
    public function getPurchasedCarts(IProfileUser $user);

    /**
     * Undocumented function
     *
     * @param IProfileUser $user
     * @param Domain $domain
     * @param integer|Product $productId
     * @return boolean
     */
    public function isProductOnPurchasedList(IProfileUser $user, $product);
}
