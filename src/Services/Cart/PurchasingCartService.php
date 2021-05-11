<?php

namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Repositories\IProductRepository;
use Larapress\ECommerce\Services\GiftCodes\IGiftCodeService;
use Larapress\ECommerce\Services\Wallet\IWalletService;

class PurchasingCartService implements IPurchasingCartService
{

    /** @var IGiftCodeService */
    protected $giftService;
    /** @var ICartService */
    protected $cartService;
    public function __construct(ICartService $cartService, IGiftCodeService $giftService)
    {
        $this->cartService = $cartService;
        $this->giftService = $giftService;
    }
    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param int $currency
     *
     * @return Response
     */
    public function updatePurchasingCart(CartUpdateRequest $request, IECommerceUser $user, int $currency)
    {
        /** @var Cart $cart */
        $cart = $this->getPurchasingCart($user, $currency);

        $cart->setPeriodicProductIds($request->getPeriodicIds());
        $cart->setUseBalance($request->getUseBalance());

        $giftDetails = null;
        if (!is_null($request->getGiftCode())) {
            $giftDetails = $this->giftService->getGiftDetailsForCart(
                $user,
                $cart,
                $request->getGiftCode()
            );
            if (!is_null($giftDetails)) {
                $cart->setGiftCodeUsage($giftDetails);
            }
        }
        $this->cartService->updateCartAmountFromDataAndProducts($cart);

        /** @var IWalletService $walletService */
        $walletService = app(IWalletService::class);
        $balance = $walletService->getUserBalance($user, $currency);

        $this->resetPurchasingCache($user->id);
        $cart = $this->getPurchasingCart($user, $currency);
        CRUDUpdated::dispatch(
            Auth::user(),
            $cart,
            CartCRUDProvider::class,
            Carbon::now()
        );

        return [
            'cart' => $cart,
            'balance' => $balance,
        ];
    }


    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param ICartItem $cartItem
     * @param int $currency
     *
     * @return Cart
     */
    public function addItemToPurchasingCart(CartContentModifyRequest $request, IECommerceUser $user, ICartItem $product, int $currency)
    {
        /** @var Cart */
        $cart = $this->getPurchasingCart($user, $currency);

        $quantity = $product->isQuantized() ? $request->getQuantity() : 1;
        $products = $cart->products;
        $existingPivot = null;
        $existingItemsIds = [];
        foreach ($products as $existingProd) {
            $existingItemsIds[] = $existingProd->id;
            if ($product->id === $existingProd->id) {
                $existingPivot = $existingProd;
                if ($product->isQuantized()) {
                    $quantity += $existingProd->pivot->data['quantity'];
                }
            }
        }

        // check if items parent is already in cart
        if ($product->parent) {
            /** @var IProductRepository $prodService */
            $prodService = app(IProductRepository::class);
            $ancestors = $prodService->getProductAncestors($product);

            foreach ($ancestors as $ans) {
                if (in_array($ans->id, $existingItemsIds)) {
                    // parent object already in cart, can not add this item
                    throw new AppException(AppException::ERR_INVALID_QUERY);
                }
            }
        }

        $itemPrice = $product->price($cart->currency);
        // remove item children if already in the cart
        if (count($product->children)) {
            $childIds = $product->children->pluck('id');
            $cart->products()->detach($childIds);
        }
        // update quantiy if already exists in cart
        if (!is_null($existingPivot)) {
            $existingPivot->pivot->update([
                'data' => [
                    'amount' => $itemPrice,
                    'currency' => $cart->currency,
                    'quantity' => $quantity,
                ],
            ]);
        } else {
            $cart->products()->attach($product->model(), [
                'data' => [
                    'amount' => $itemPrice,
                    'currency' => $cart->currency,
                    'quantity' => $quantity,
                ],
            ]);
            $cart->load('products');
        }

        $products = $cart->products;
        $this->cartService->updateCartAmountFromDataAndProducts($cart, $products);

        $this->resetPurchasingCache($user->id);
        $cart = $this->getPurchasingCart($user, $currency);

        CRUDUpdated::dispatch(
            Auth::user(),
            $cart,
            CartCRUDProvider::class,
            Carbon::now()
        );

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param ICartItem $cartItem
     * @return Cart
     */
    public function removeItemFromPurchasingCart(CartContentModifyRequest $request, IECommerceUser $user, ICartItem $product, int $currency)
    {
        /** @var Cart */
        $cart = $this->getPurchasingCart($user, $currency);

        /** @var ICartItem $existingProd */
        $existingProd = $cart->products()->wherePivot('product_id', $product->id)->first();
        if (is_null($existingProd)) {
            return $cart;         // this item does not exist in our users current cart
        }

        $existingProdData = $existingProd->pivot->data;

        $removeQuantity = $product->isQuantized() ? $request->getQuantity() : 1;

        if ($existingProdData['quantity'] <= $removeQuantity) {
            $cart->products()->detach($product->id);
        } else {
            $existingProdData['quantity'] -= $removeQuantity;
            $existingProd->pivot->update([
                'data' => $existingProdData
            ]);
        }

        $cart->load('products');
        $this->cartService->updateCartAmountFromDataAndProducts($cart);

        $this->resetPurchasingCache($user->id);
        $cart = $this->getPurchasingCart($user, $currency);

        CRUDUpdated::dispatch(
            Auth::user(),
            $cart,
            CartCRUDProvider::class,
            Carbon::now()
        );


        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param integer $currency
     * @return Cart
     */
    public function getPurchasingCart(IECommerceUser $user, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchase-cart',
            function () use ($user, $currency) {
                if (is_null($user->getMembershipDomainId())) {
                    throw new AppException(AppException::ERR_USER_HAS_NO_DOMAIN);
                }

                $cart = Cart::query()
                    ->with(['products'])
                    ->where('customer_id', $user->id)
                    ->where('domain_id', $user->getMembershipDomainId())
                    ->where('currency', $currency)
                    ->where('flags', '&', Cart::FLAGS_USER_CART) // is a user cart
                    ->whereRaw('(flags & ' . Cart::FLGAS_FORWARDED_TO_BANK . ') = 0') // has never been forwarded to bank page
                    ->where('status', '=', Cart::STATUS_UNVERIFIED)
                    ->first();

                if (is_null($cart)) {
                    $cart = Cart::create([
                        'amount' => 0,
                        'currency' => $currency,
                        'customer_id' => $user->id,
                        'domain_id' => $user->getMembershipDomainId(),
                        'flags' => Cart::FLAGS_USER_CART,
                        'status' => Cart::STATUS_UNVERIFIED,
                        'data' => []
                    ]);
                }
                return $cart;
            },
            ['purchasing-cart:' . $user->id],
            null
        );
    }

    /**
     * Undocumented function
     *
     * @param int $userId
     * @return void
     */
    protected function resetPurchasingCache($userId)
    {
        Cache::tags(['purchasing-cart:' . $userId])->flush();
    }
}
