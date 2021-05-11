<?php


namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Repositories\IProductRepository;
use Larapress\ECommerce\Services\GiftCodes\IGiftCodeService;
use Larapress\ECommerce\Services\Cart\CartGiftDetails;
use Larapress\ECommerce\Services\Wallet\IWalletService;
use Larapress\CRUD\BaseFlags;
use Larapress\CRUD\Events\CRUDCreated;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\ECommerce\CRUD\CartCRUDProvider;

class CartService implements ICartService
{
    /** @var IWalletService */
    protected $walletService;
    /** @var IGiftCodeService */
    protected $giftService;
    public function __construct(IGiftCodeService $giftService, IWalletService $walletService)
    {
        $this->giftService = $giftService;
        $this->walletService = $walletService;
    }

    /**
     * Undocumented function
     *
     * @param Cart $cart
     * @param Carbon|string|null $purchaseTimestamp
     * @return Cart
     */
    public function markCartPurchased(Cart $cart, $purchaseTimestamp = null)
    {
        if (BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART)) {
            return $this->markPeriodicPaymentCartPurchased($cart);
        }

        /** @var IECommerceUser $user */
        $user = $cart->customer;
        if (is_null($purchaseTimestamp)) {
            $purchaseTimestamp = Carbon::now();
        } else {
            if (is_string($purchaseTimestamp)) {
                $purchaseTimestamp = Carbon::parse($purchaseTimestamp);
            }
        }

        $cart->setPeriodStart($purchaseTimestamp);
        $giftDetails = $cart->getGiftCodeUsage();
        /** @var CartGiftDetails $giftDetails */
        if (!is_null($giftDetails)) {
            $giftDetails = $this->giftService->getGiftUsageDetailsForCart(
                $user,
                $cart,
                $giftDetails->code,
            );
            $cart->setGiftCodeUsage($giftDetails);
        }

        $userVirtualBalance = $this->walletService->getUserVirtualBalance($user, $cart->currency);

        $amount = 0;
        $periodsOffPercent = !is_null($giftDetails) ? $giftDetails->percent : null;
        $purchaseDetails = [];
        /** @var Product[] $products */
        $products = $cart->products;
        foreach ($products as $product) {
            $itemPrice = 0;
            $itemQuantity = isset($product->pivot->data['quantity']) ? $product->pivot->data['quantity'] : 1;
            $detail = new CartProductPurchaseDetails([]);
            if ($cart->isProductInPeriodicIds($product)) {
                $itemPrice = $product->pricePeriodic($cart->currency) * $itemQuantity;
                $amount += $itemPrice;
                $detail->periodsAmount = $product->getPeriodicPurchaseAmount();
                $detail->periodsDuration = $product->getPeriodicPurchaseDuration();
                $detail->periodsEnds = $product->getPeriodicPurchaseEndDate();
                $detail->periodsCount = $product->getPeriodicPurchaseCount();
            } else {
                $itemPrice = $product->price($cart->currency) * $itemQuantity;
                $amount += $itemPrice;
            }
            $detail->salePrice = $itemPrice;
            $detail->periodsOffPercent = $periodsOffPercent;
            $detail->quantity = $product->pivot->data['quantity'];

            $totalSaleAmount = $itemPrice;
            if ($cart->isProductInPeriodicIds($product)) {
                $totalSaleAmount += ($detail->periodsCount * $detail->periodsAmount);
            }
            $detail->goodsSale = $totalSaleAmount;
            $detail->offAmount = isset($giftDetails->products[$product->id]) ? $giftDetails->products[$product->id] : 0;

            $paidCurrencyForProduct = $detail->salePrice - $detail->offAmount;
            if ($userVirtualBalance > 0) {
                if ($userVirtualBalance > $paidCurrencyForProduct) {
                    $userVirtualBalance -= $paidCurrencyForProduct;
                    $detail->virtualSale = $paidCurrencyForProduct;
                    $detail->realSale = 0;
                } else {
                    $detail->virtualSale = $userVirtualBalance;
                    $detail->realSale = $paidCurrencyForProduct - $detail->virtualSale;
                    $userVirtualBalance = 0;
                }
            } else {
                $detail->virtualSale = 0;
                $detail->realSale = $paidCurrencyForProduct;
            }

            $purchaseDetails[$product->id] = $detail;
        }

        $hasPeriodicProducts = $cart->getPeriodicProductsCount() > 0;
        $cart->update([
            'status' => Cart::STATUS_ACCESS_COMPLETE,
            'flags' => $cart->flags | Cart::FLAGS_EVALUATED | ($hasPeriodicProducts ? Cart::FLAGS_HAS_PERIODS : 0),
        ]);

        CartPurchasedEvent::dispatch($cart, time());
        CRUDUpdated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());
        $this->resetPurchasedCache($user->id);

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @return ICart
     */
    protected function markPeriodicPaymentCartPurchased(ICart $cart)
    {
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param array $ids
     * @param int $currency
     * @param GiftCode|null $giftCode
     * @param int|null $amount
     * @param string|null $desc
     *
     * @return Cart
     */
    public function createCartWithProductIDs(IECommerceUser $user, array $ids, $currency, $giftCode = null, $amount = null, $desc = null)
    {
        /** @var Cart $cart */
        $cart = Cart::create([
            'customer_id' => $user->id,
            'domain_id' => $user->getMembershipDomainId(),
            'amount' => 0,
            'currency' => $currency,
            'flags' => Cart::FLAGS_SYSTEM_API,
            'status' => Cart::STATUS_UNVERIFIED,
        ]);

        $cart->setDescription($desc);

        if (!is_null($giftCode)) {
            $details = $this->giftService->getGiftUsageDetailsForCart($user, $cart, $giftCode->code);
            if (!is_null($details)) {
                $cart->setGiftCodeUsage($details);
            }
        }

        $products = Product::whereIn('id', $ids)->get()->keyBy('id');
        foreach ($ids as $id) {
            $cart->products()->attach($id, [
                'data' => (array) (new CartPivotDetails([
                    'amount' => $products[$id]->price(),
                    'quantity' => 1,
                ]))
            ]);
        }
        $cartAmount = $amount;
        if (is_null($cartAmount)) {
            $cart = $this->updateCartAmountFromDataAndProducts($cart);
        } else {
            $cart->update([
                'amount' => $cartAmount
            ]);
        }
        CRUDCreated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return array
     */
    public function getPurchasedItemIds(IECommerceUser $user)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchased-cart-items',
            function () use ($user) {
                $carts = $this->getPurchasedCarts($user);
                $ids = [];
                $groups = [];
                foreach ($carts as $cart) {
                    foreach ($cart->products as $item) {
                        $ids[] = $item['id'];
                        if (!is_null($item['group']) && !empty($item['group'])) {
                            $groups[] = $item['group'];
                        }
                    }
                }

                if (
                    !is_null(config('larapress.lcms.teacher_support_form_id')) &&
                    $user->hasRole(config('larapress.lcms.owner_role_id'))
                ) {
                    $ids = array_merge($ids, $user->getOwenedProductsIds());
                }

                if (count($groups) > 0) {
                    // include product ids with same group
                    $groupedIds = Product::select('id')->whereIn('group', $groups)->get()->pluck('id')->toArray();
                    $ids = array_merge($ids, $groupedIds);
                }

                return $ids;
            },
            ['purchased-cart:' . $user->id],
            null
        );
    }


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return array
     */
    public function getPurchasedCarts(IECommerceUser $user)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchased-cart',
            function () use ($user) {
                return  Cart::query()
                    ->with(['products'])
                    ->where('customer_id', $user->id)
                    ->where('domain_id', $user->getMembershipDomainId())
                    ->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED])
                    ->get();
            },
            ['purchased-cart:' . $user->id],
            null,
        );
    }


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer|Product $product
     * @return boolean
     */
    public function isProductOnPurchasedList(IECommerceUser $user, $product)
    {
        if (is_numeric($product)) {
            $product = Product::find($product);
        }

        if (!is_null($product->parent_id)) {
            /** @var IProductRepository */
            $productRepo = app(IProductRepository::class);
            $ancestors = $productRepo->getProductAncestorIds($product);
        } else {
            $ancestors = [];
        }
        $ancestors[] = $product->id;

        $paidIds = $this->getPurchasedItemIds($user);
        foreach ($ancestors as $parentId) {
            if (in_array($parentId, $paidIds)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param integer|Product $product
     * @return boolean
     */
    public function isProductOnLockedList(IECommerceUser $user, $product)
    {
        if (is_object($product)) {
            $product = $product->id;
        }
        return in_array($product, $this->getLockedItemIds($user));
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @return array
     */
    public function getLockedItemIds(IECommerceUser $user)
    {
        return [];
    }


    /**
     * Undocumented function
     *
     * @param Cart $cart
     * @param null|Product[] $products
     *
     * @return Cart
     */
    public function updateCartAmountFromDataAndProducts(Cart $cart, $products = null)
    {
        $amount = 0;

        if (is_null($products)) {
            /** @var Product[] $items */
            $products = $cart->products;
        }

        /** @var IECommerceUser $user */
        $user = $cart->customer;

        $giftDetails = null;
        /** @var CartGiftDetails $giftDetails */
        if (!is_null($cart->getGiftCodeUsage())) {
            $giftDetails = $this->giftService->getGiftUsageDetailsForCart(
                $user,
                $cart,
                $giftDetails->code,
            );
            if (!is_null($giftDetails)) {
                $cart->setGiftCodeUsage($giftDetails);
            }
        }

        foreach ($products as $product) {
            $quantity = $product->isQuantized() ? $product->pivot->data['quantity'] : 1;
            if ($cart->isProductInPeriodicIds($product)) {
                $amount += $product->pricePeriodic($cart->currency) * $quantity;
            } else {
                $amount += $product->price($cart->currency) * $quantity;
            }
        }

        if (!is_null($giftDetails)) {
            $amount -= $giftDetails->amount;
        }
        $amount = max(0, $amount);

        $cart->update([
            'amount' => $amount,
        ]);

        return $cart;
    }


    /**
     * Undocumented function
     *
     * @param int $userId
     * @return void
     */
    protected function resetPurchasedCache($userId)
    {
        Cache::tags(['purchased-cart:' . $userId])->flush();
    }
}
