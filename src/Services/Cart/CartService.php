<?php


namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\GiftCodes\IGiftCodeService;
use Larapress\ECommerce\Services\Cart\Base\CartGiftDetails;
use Larapress\ECommerce\Services\Wallet\IWalletService;
use Larapress\CRUD\Events\CRUDCreated;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\Models\WalletTransaction;
use Larapress\ECommerce\Services\Cart\Base\CartInstallmentPaymentDetails;
use Larapress\ECommerce\Services\Cart\Base\CartInstallmentPurchaseDetails;
use Larapress\ECommerce\Services\Cart\Base\CartProductPurchaseDetails;
use Larapress\ECommerce\Services\Product\IProductRepository;
use Illuminate\Support\Collection;

class CartService implements ICartService
{
    /** @var IWalletService */
    protected $walletService;
    /** @var IGiftCodeService */
    protected $giftService;
    /** @var IInstallmentCartService */
    protected $installmentService;

    public function __construct(IGiftCodeService $giftService, IWalletService $walletService, IInstallmentCartService $installmentService)
    {
        $this->giftService = $giftService;
        $this->walletService = $walletService;
        $this->installmentService = $installmentService;
    }

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Carbon|string|null $purchaseTimestamp

     * @return ICart
     */
    public function markCartPurchased(ICart $cart, $purchaseTimestamp = null)
    {
        if ($cart->isSingleInstallmentCart()) {
            // this is an aggregated cart for current periods payments in all carts
            $carts = $cart->getSingleInstallmentOriginalCarts();
            foreach ($carts as $singleCart) {
                $this->markCartPurchased($singleCart, $purchaseTimestamp);
            }

            $cart->flags |= Cart::FLAGS_EVALUATED;
            $cart->status = Cart::STATUS_ACCESS_COMPLETE;
            /** @var Cart $cart */
            $cart->update();
        } else {
            if ($cart->isPeriodicPaymentCart()) {
                $originalCart = $cart->getPeriodicPaymentOriginalCart();
                if ($originalCart->isCustomPeriodicPayment()) {
                    return $this->markCustomPeriodicPaymentCartPurchased($cart, $originalCart, $purchaseTimestamp);
                } else {
                    return $this->markSystemPeriodicPaymentCartPurchased($cart, $originalCart, $purchaseTimestamp);
                }
            } else {
                if ($cart->isCustomPeriodicPayment()) {
                    return $this->markCustomizedCartPurchased($cart, $purchaseTimestamp);
                } else {
                    return $this->markSystemCartPurchased($cart, $purchaseTimestamp);
                }
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Carbon|string|null $purchaseTimestamp
     *
     * @return ICart
     */
    protected function markSystemCartPurchased(ICart $cart, $purchaseTimestamp)
    {
        /** @var IECommerceUser $user */
        $user = $cart->customer;

        // update purchase timestamp, used in calculating periods
        $purchaseTimestamp = $this->markCartPurchaseTimestamp($cart, $purchaseTimestamp);

        // update purchase gift code usage details
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

        // store cart amount for products sale share calulcation
        $cartRemainingAmounForProductShare = $cart->amount;

        /** @var Product[] $products */
        $products = $cart->products;

        foreach ($products as $product) {
            $detail = new CartProductPurchaseDetails([]);

            // calculate product purchase details as a snapshot
            $itemPrice = 0;
            $itemQuantity = isset($product->pivot->data['quantity']) ? $product->pivot->data['quantity'] : 1;
            if ($cart->isProductInPeriodicIds($product)) {
                $itemPrice = $product->pricePeriodic($cart->currency) * $itemQuantity;
                $detail->periodsAmount = $product->getPeriodicPurchaseAmount();
                $detail->periodsDuration = $product->getPeriodicPurchaseDuration();
                $detail->periodsEnds = $product->getPeriodicPurchaseEndDate();
                $detail->periodsCount = $product->getPeriodicPurchaseCount();
                $detail->hasPeriods = true;
            } else {
                $detail->hasPeriods = false;
                $itemPrice = $product->price($cart->currency) * $itemQuantity;
            }
            $detail->amount = $itemPrice;
            $detail->periodsOffPercent = !is_null($giftDetails) ? $giftDetails->percent : 0;
            $detail->quantity = $itemQuantity;

            // calculate product gift usage
            if (!is_null($giftDetails) && (!$giftDetails->restrict_products || isset($giftDetails->products[$product->id]))) {
                // calculate gift off amounts
                $detail->offAmount = $giftDetails->products[$product->id];
                $detail->periodsOffPercent = $giftDetails->percent;
                $detail->periodsPaymentAmount = $detail->periodsAmount * (1 - $detail->periodsOffPercent);
            } else {
                // no gift is used
                $detail->offAmount = 0;
                $detail->periodsOffPercent = 0;
                $detail->periodsPaymentAmount = $cart->isProductInPeriodicIds($product) ? $detail->periodsAmount : 0;
            }

            // total periodic payments to be paid
            $detail->periodsTotalPayment = $detail->periodsPaymentAmount * $detail->periodsCount;

            // calculate product share in currency with cart amount
            $productCurrencyPayAmount = $itemPrice - $detail->offAmount;
            $detail->currencyPaid = 0;
            if ($cartRemainingAmounForProductShare >= $productCurrencyPayAmount) {
                $cartRemainingAmounForProductShare -= $productCurrencyPayAmount;
                $detail->currencyPaid = $productCurrencyPayAmount;
            } else {
                $detail->currencyPaid  = $cartRemainingAmounForProductShare;
                $cartRemainingAmounForProductShare = 0;
            }

            $this->calculatePurchaseDetailsForProductInCartWithPurchaseDetails(
                $user,
                $cart,
                $product,
                $detail
            );

            // save product purchase details
            $pivotData = $product->pivot->data;
            $pivotData = array_merge($pivotData, $detail->toArray());
            $product->pivot->update([
                'data' => $pivotData,
            ]);
        }

        /** @var Cart $cart */
        $hasPeriodicProducts = $cart->getPeriodicProductsCount() > 0;
        $cart->status = Cart::STATUS_ACCESS_COMPLETE;
        $cart->flags = $cart->flags | Cart::FLAGS_EVALUATED | ($hasPeriodicProducts ? Cart::FLAGS_HAS_PERIODS : 0);
        $cart->update();

        // create cart installments if it has periodic payments
        if ($cart->hasPeriodicProducts()) {
            // reload products for pivot updates
            $cart->load('products');
            $this->installmentService->updateInstallmentsForCart($cart);
        }

        // fire events
        $this->fireCartPurchaseEvents($user, $cart, $purchaseTimestamp);

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Carbon|string|null $purchaseTimestamp
     *
     * @return ICart
     */
    protected function markCustomizedCartPurchased(ICart $cart, $purchaseTimestamp)
    {
        /** @var IECommerceUser $user */
        $user = $cart->customer;

        $purchaseTimestamp = $this->markCartPurchaseTimestamp($cart, $purchaseTimestamp);

        // store cart amount for products sale share calulcation
        $cartRemainingAmounForProductShare = $cart->amount;

        /** @var Product[] $products */
        $products = $cart->products;

        $cartProductsCount = count($products);

        foreach ($products as $product) {
            $detail = new CartProductPurchaseDetails([]);

            // calculate product purchase details as a snapshot
            $itemPrice = 0;
            $itemQuantity = isset($product->pivot->data['quantity']) ? $product->pivot->data['quantity'] : 1;
            if ($cart->isProductInPeriodicIds($product)) {
                $itemPrice = $product->pricePeriodic($cart->currency) * $itemQuantity;
                $detail->hasPeriods = true;
            } else {
                $detail->hasPeriods = false;
                $itemPrice = $product->price($cart->currency) * $itemQuantity;
            }
            $detail->amount = $itemPrice;
            $detail->periodsOffPercent = 0;
            $detail->quantity = $itemQuantity;

            // calculate product share in currency with cart amount
            $detail->currencyPaid = $cartRemainingAmounForProductShare / $cartProductsCount;

            // calculate product periodic currency payments
            $detail->periodsPaymentAmount = 0;
            $detail->periodsTotalPayment = 0;
            if ($cart->isProductInPeriodicIds($product)) {
                $orderedPeriods = $cart->getCustomPeriodsOrdered();
                $detail->periodsCount = count($orderedPeriods);
                foreach ($orderedPeriods as $orderedPeriod) {
                    $detail->periodsTotalPayment += ($orderedPeriod->amount / $cartProductsCount);
                }
            }

            // calculate virtual/real share
            $this->calculatePurchaseDetailsForProductInCartWithPurchaseDetails(
                $user,
                $cart,
                $product,
                $detail
            );

            // save product purchase details
            $pivotData = $product->pivot->data;
            $pivotData = array_merge($pivotData, $detail->toArray());
            $product->pivot->update([
                'data' => $pivotData,
            ]);
        }

        /** @var Cart $cart */
        $hasPeriodicProducts = $cart->getPeriodicProductsCount() > 0;
        $cart->status = Cart::STATUS_ACCESS_COMPLETE;
        $cart->flags = $cart->flags | Cart::FLAGS_EVALUATED | ($hasPeriodicProducts ? Cart::FLAGS_HAS_PERIODS : 0);
        $cart->update();

        // create cart installments if it has periodic payments
        if ($cart->hasPeriodicProducts()) {
            // reload products relation and grab pivot changes
            $cart->load('products');
            $this->installmentService->updateInstallmentsForCart($cart);
        }

        // fire cart events
        $this->fireCartPurchaseEvents($user, $cart, $purchaseTimestamp);

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param ICart $originalCart
     * @param Carbon|string|null $purchaseTimestamp
     *
     * @return ICart
     */
    protected function markCustomPeriodicPaymentCartPurchased(ICart $cart, ICart $originalCart, $purchaseTimestamp)
    {
        /** @var IECommerceUser $user */
        $user = $cart->customer;

        $purchaseTimestamp = $this->markCartPurchaseTimestamp($cart, $purchaseTimestamp);

        /** @var Product[] $products */
        $products = $cart->products;
        foreach ($products as $product) {
            $purchaseDetails = new CartInstallmentPurchaseDetails($product->pivot->data);
            $purchaseDetails->currencyPaid = $purchaseDetails->amount;

            // calculate real/virtual share
            $this->calculatekInstallmentPurchaseDetailsForProductInCartWithPurchaseDetails($user, $cart, $product, $purchaseDetails);

            // save product purchase details
            $pivotData = $product->pivot->data;
            $pivotData = array_merge($pivotData, $purchaseDetails->toArray());
            $product->pivot->update([
                'data' => $pivotData,
            ]);
        }

        // update periodic payment cart
        $cart->status = Cart::STATUS_ACCESS_COMPLETE;
        $cart->flags = $cart->flags | Cart::FLAGS_EVALUATED;
        /** @var Cart $cart */
        $cart->update();

        // update original cart custom installments records
        $cartInstallmentPaymentDetails = new CartInstallmentPaymentDetails($cart->data['periodic_pay']);
        $orderedPeriods = $originalCart->getCustomPeriodsOrdered();
        $orderedPeriods[$cartInstallmentPaymentDetails->index]->status = ICart::CustomAccessStatusPaid;
        $orderedPeriods[$cartInstallmentPaymentDetails->index]->payment_paid_at = $cart->getPeriodStart();
        $originalCart->setCustomPeriodInstallments($orderedPeriods);

        /** @var Cart $originalCart */
        $originalCart->update();

        // update purchased product details on original cart (update paid periods count)
        $products = $originalCart->products;
        foreach ($products as $product) {
            $purchaseDetails = new CartProductPurchaseDetails($product->pivot->data);
            $purchaseDetails->paidPeriods += 1;
            $product->pivot->update([
                'data' => $purchaseDetails->toArray()
            ]);
        }

        // update original cart installments
        // reload products relation and grab pivot changes
        $originalCart->load('products');
        $this->installmentService->updateInstallmentsForCart($originalCart);

        // fire events about periodic payment cart
        $this->fireCartPurchaseEvents($user, $cart, $purchaseTimestamp);

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param ICart $originalCart
     * @param Carbon|string|null $purchaseTimestamp
     *
     * @return ICart
     */
    protected function markSystemPeriodicPaymentCartPurchased(ICart $cart, ICart $originalCart, $purchaseTimestamp)
    {
        /** @var IECommerceUser $user */
        $user = $cart->customer;

        $purchaseTimestamp = $this->markCartPurchaseTimestamp($cart, $purchaseTimestamp);

        /** @var Product[]|Collection $products */
        $products = $cart->products;
        $installmentProductIds = $products->pluck('id');
        foreach ($products as $product) {
            $purchaseDetails = new CartInstallmentPurchaseDetails($product->pivot->data);
            $purchaseDetails->currencyPaid = $purchaseDetails->amount;

            // calculate real/virtual share
            $this->calculatekInstallmentPurchaseDetailsForProductInCartWithPurchaseDetails($user, $cart, $product, $purchaseDetails);

            // save product purchase details
            $pivotData = $product->pivot->data;
            $pivotData = array_merge($pivotData, $purchaseDetails->toArray());
            $product->pivot->update([
                'data' => $pivotData,
            ]);
        }

        // update periodic payment cart status
        $cart->status = Cart::STATUS_ACCESS_COMPLETE;
        $cart->flags = $cart->flags | Cart::FLAGS_EVALUATED;
        /** @var Cart $cart */
        $cart->update();

        // update original cart products purchase details (update paid periods)
        // only for products that are in this periodic payment
        /** @var Product[] */
        $orignalCartProducts = $originalCart->products;
        foreach ($orignalCartProducts as $originalCartProduct) {
            if ($installmentProductIds->contains($originalCartProduct->id)) {
                $purchaseDetails = new CartProductPurchaseDetails($originalCartProduct->pivot->data);
                $purchaseDetails->paidPeriods += 1;
                $originalCartProduct->pivot->update([
                    'data' => $purchaseDetails->toArray()
                ]);
            }
        }

        // reload products relation for original cart; mek pivot updates available
        /** @var Cart $originalCart */
        $originalCart->load('products');
        // update original cart installments
        $this->installmentService->updateInstallmentsForCart($originalCart);

        // fire events about periodic payment cart
        $this->fireCartPurchaseEvents($user, $cart, $purchaseTimestamp);

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param null|Carbon|string $purchaseTimestamp
     *
     * @return Carbon
     */
    protected function markCartPurchaseTimestamp(ICart $cart, $purchaseTimestamp): Carbon
    {
        if (is_null($purchaseTimestamp)) {
            $purchaseTimestamp = Carbon::now();
        } else {
            if (is_string($purchaseTimestamp)) {
                $purchaseTimestamp = Carbon::parse($purchaseTimestamp);
            }
        }
        $cart->setPeriodStart($purchaseTimestamp);

        return $purchaseTimestamp;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param Cart $cart
     * @param Product $product
     * @param CartProductPurchaseDetails $detail
     *
     * @return void
     */
    protected function calculatePurchaseDetailsForProductInCartWithPurchaseDetails(
        IECommerceUser $user,
        Cart $cart,
        Product $product,
        CartProductPurchaseDetails $detail,
    ) {
        // calculate real/virtual/goods
        if ($detail->currencyPaid > 0) {
            // user virtual balance
            $userVirtualBalance = $this->walletService->getUserVirtualBalance($user, $cart->currency);

            // calculate goods sale
            if ($cart->isProductInPeriodicIds($product)) {
                $detail->goodsSale = $detail->currencyPaid + $detail->periodsTotalPayment;
            } else {
                $detail->goodsSale = $detail->currencyPaid;
            }

            // calculate virtual and real sale
            if ($userVirtualBalance > 0) {
                if ($userVirtualBalance > $detail->currencyPaid) {
                    $userVirtualBalance -= $detail->currencyPaid;
                    $detail->virtualSale = $detail->currencyPaid;
                    $detail->realSale = 0;
                } else {
                    $detail->virtualSale = $userVirtualBalance;
                    $detail->realSale = $detail->currencyPaid - $detail->virtualSale;
                    $userVirtualBalance = 0;
                }
            } else {
                $detail->virtualSale = 0;
                $detail->realSale = $detail->currencyPaid;
            }

            if ($detail->virtualSale > 0) {
                $this->walletService->addBalanceForUser(
                    $user,
                    -1 * $detail->virtualSale,
                    $cart->currency,
                    WalletTransaction::TYPE_VIRTUAL_MONEY,
                    WalletTransaction::FLAGS_CART_PURCHASE,
                    trans('larapress::ecommerce.banking.messages.wallet_descriptions.cart_purchased_product', [
                        'product' => $product->data['title'],
                        'cart_id' => $cart->id,
                    ]),
                    [
                        'cart_id' => $cart->id,
                    ]
                );
            }
            if ($detail->realSale > 0) {
                $this->walletService->addBalanceForUser(
                    $user,
                    -1 * $detail->realSale,
                    $cart->currency,
                    WalletTransaction::TYPE_REAL_MONEY,
                    WalletTransaction::FLAGS_CART_PURCHASE,
                    trans('larapress::ecommerce.banking.messages.wallet_descriptions.cart_purchased_product', [
                        'product' => $product->data['title'],
                        'cart_id' => $cart->id,
                    ]),
                    [
                        'cart_id' => $cart->id,
                    ]
                );
            }
        }
    }


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param Cart $cart
     * @param Product $product
     * @param CartProductPurchaseDetails $detail
     *
     * @return void
     */
    protected function calculatekInstallmentPurchaseDetailsForProductInCartWithPurchaseDetails(
        IECommerceUser $user,
        Cart $cart,
        Product $product,
        CartInstallmentPurchaseDetails $detail,
    ) {
        // user virtual balance
        $userVirtualBalance = $this->walletService->getUserVirtualBalance($user, $cart->currency);

        // calculate real/virtual/goods
        if ($detail->currencyPaid  > 0) {
            // calculate virtual and real sale
            if ($userVirtualBalance > 0) {
                if ($userVirtualBalance > $detail->currencyPaid) {
                    $userVirtualBalance -= $detail->currencyPaid;
                    $detail->virtualSale = $detail->currencyPaid;
                    $detail->realSale = 0;
                } else {
                    $detail->virtualSale = $userVirtualBalance;
                    $detail->realSale = $detail->currencyPaid - $detail->virtualSale;
                    $userVirtualBalance = 0;
                }
            } else {
                $detail->virtualSale = 0;
                $detail->realSale = $detail->currencyPaid;
            }

            if ($detail->virtualSale > 0) {
                $this->walletService->addBalanceForUser(
                    $user,
                    -1 * $detail->virtualSale,
                    $cart->currency,
                    WalletTransaction::TYPE_VIRTUAL_MONEY,
                    WalletTransaction::FLAGS_CART_PURCHASE,
                    trans('larapress::ecommerce.banking.messages.wallet_descriptions.cart_purchased_product', [
                        'product' => $product->data['title'],
                        'cart_id' => $cart->id,
                    ]),
                    [
                        'cart_id' => $cart->id,
                    ]
                );
            }
            if ($detail->realSale > 0) {
                $this->walletService->addBalanceForUser(
                    $user,
                    -1 * $detail->realSale,
                    $cart->currency,
                    WalletTransaction::TYPE_REAL_MONEY,
                    WalletTransaction::FLAGS_CART_PURCHASE,
                    trans('larapress::ecommerce.banking.messages.wallet_descriptions.cart_purchased_product', [
                        'product' => $product->data['title'],
                        'cart_id' => $cart->id,
                    ]),
                    [
                        'cart_id' => $cart->id,
                    ]
                );
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param ICart $cart
     * @param int|string|Carbon $purchaseTimestamp
     *
     * @return void
     */
    protected function fireCartPurchaseEvents(IECommerceUser $user, ICart $cart, Carbon $purchaseTimestamp)
    {
        CartPurchasedEvent::dispatch($cart, $purchaseTimestamp);
        CRUDUpdated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());
        /** @var IPurchasingCartService */
        $purchasingCartService = app(IPurchasingCartService::class);
        $purchasingCartService->resetPurchasingCache($user->id);
        $this->walletService->resetBalanceCache($user->id);
        $this->resetPurchasedCache($user->id);
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
                'data' => [
                    'amount' => $products[$id]->price(),
                    'quantity' => 1,
                ]
            ]);
        }
        $cartAmount = $amount;
        if (is_null($cartAmount)) {
            $amount = $this->calculateCartAmountFromDataAndProducts($cart);
        }
        $cart->amount = $cartAmount;
        $cart->update();

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
            ['purchased-cart:' . $user->id],
            3600,
            true,
            function () use ($user) {
                $ids = [];
                $groups = [];

                // find purchased carts and include them, with their groups
                $carts = $this->getPurchasedCarts($user);
                foreach ($carts as $cart) {
                    foreach ($cart->products as $item) {
                        $ids[] = $item['id'];
                        if (!is_null($item['group']) && !empty($item['group'])) {
                            $groups[] = $item['group'];
                        }
                    }
                }

                // include products with same group
                if (count($groups) > 0) {
                    // include product ids with same group
                    $groupedIds = Product::query()->select('id')->whereIn('group', $groups)->get()->pluck('id')->toArray();
                    $ids = array_merge($ids, $groupedIds);
                }

                // include owned products by user group
                if ($user->hasRole(config('larapress.ecommerce.product_owner_role_ids'))
                ) {
                    $ids = array_merge($ids, $user->getOwenedProductsIds());
                }

                // include free for all products
                $freeProducts = Product::query()->where(function ($query) {
                    $query->orWhereJsonLength('data->pricing', 0);
                    $query->orWhereRaw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.pricing")) IS NULL');
                })->whereNull('parent_id')->get()->pluck('id')->toArray();
                $ids = array_merge($ids, $freeProducts);

                return $ids;
            }
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
            ['purchased-cart:' . $user->id],
            3600,
            true,
            function () use ($user) {
                return  Cart::query()
                    ->with(['products'])
                    ->where('customer_id', $user->id)
                    ->whereIn('status', [Cart::STATUS_ACCESS_COMPLETE, Cart::STATUS_ACCESS_GRANTED])
                    ->get();
            },
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
            /** @var Product */
            $product = Product::find($product);
        }

        // return true if this item is a free access
        if ($product->isFree()) {
            return true;
        }

        $ancestors = [];
        if (!is_null($product->parent_id)) {
            /** @var IProductRepository */
            $productRepo = app(IProductRepository::class);
            $ancestors = $productRepo->getProductAncestorIds($product);
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
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.'.$user->id.'.locked_products',
            ['purchased-cart:' . $user->id],
            3600,
            true,
            function () use ($user) {
                $installments = $this->installmentService->getUserInstallments($user);

                $expiredProductIds = [];

                $now = Carbon::now();
                foreach ($installments as $installment) {
                    /** @var Product[] */
                    $products = $installment->products;

                    foreach ($products as $product) {
                        $purchaseDetails = new CartInstallmentPurchaseDetails($product->pivot->data);
                        if ($now->isAfter($purchaseDetails->due_date)) {
                            $expiredProductIds[] = $product->id;
                        }
                    }
                }

                return $expiredProductIds;
            }
        );
    }


    /**
     * Undocumented function
     *
     * @param ICart $cart
     *
     * @return float
     */
    public function calculateCartAmountFromDataAndProducts(ICart $cart)
    {
        $amount = 0.0;
        $products = $cart->products;

        /** @var IECommerceUser $user */
        $user = $cart->customer;

        $giftDetails = $cart->getGiftCodeUsage();
        /** @var CartGiftDetails $giftDetails */
        if (!is_null($giftDetails)) {
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

        return $amount;
    }


    /**
     * Undocumented function
     *
     * @param int $userId
     * @return void
     */
    public function resetPurchasedCache($userId)
    {
        Helpers::forgetCachedValues(['purchased-cart:' . $userId]);
    }
}
