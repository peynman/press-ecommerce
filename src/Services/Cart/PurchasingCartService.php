<?php

namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\GiftCodeUse;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Repositories\IProductRepository;
use Larapress\ECommerce\Services\Cart\DeliveryAgent\IDeliveryAgent;
use Larapress\ECommerce\Services\Cart\Requests\CartContentModifyRequest;
use Larapress\ECommerce\Services\Cart\Requests\CartUpdateRequest;
use Larapress\ECommerce\Services\GiftCodes\IGiftCodeService;
use Larapress\ECommerce\Services\Cart\DeliveryAgent\IDeliveryAgentClient;
use Larapress\ECommerce\Services\Cart\Requests\CartValidateRequest;
use Larapress\ECommerce\Services\Cart\Base\CartGiftDetails;

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
     * @param CartValidateRequest $request
     * @param IECommerceUser $user
     * @param integer $currency
     *
     * @return Cart
     */
    public function validateCartBeforeForwardingToBank(CartValidateRequest $request, IECommerceUser $user, int $currency)
    {
        /** @var Cart $cart */
        $cart = $this->getPurchasingCart($user, $currency);

        $addressId = $request->getDeliveryAddressId();
        if (!is_null($addressId)) {
            if ($cart->getDeliveryAddressId() !== $addressId) {
                throw new AppException(AppException::ERR_INVALID_QUERY);
            }
        }

        $agent = $request->getDeliveryAgentName();
        if (!is_null($agent)) {
            if ($cart->getDeliveryAgentName() !== $agent) {
                throw new AppException(AppException::ERR_INVALID_QUERY);
            }
        }

        $giftCode = $request->getGiftCode();
        if (!is_null($giftCode)) {
            /** @var CartGiftDetails */
            $usage = $cart->getGiftCodeUsage();
            if (!is_null($usage)) {
                if ($usage->code !== $giftCode) {
                    throw new AppException(AppException::ERR_INVALID_QUERY);
                }
            } else {
                throw new AppException(AppException::ERR_INVALID_QUERY);
            }
        }

        if ($request->getUseBalance() !== $cart->getUseBalance()) {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }

        $reqProducts = Collection::make($request->getProducts());
        $reqProductIds = $reqProducts->pluck('id');
        $periodicIds = $request->getPeriodicIds();
        $products = $cart->products;
        $productIds = $products->pluck('id');

        if ($productIds->count() !== count($reqProducts)) {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }

        foreach ($products as $product) {
            if (!$reqProductIds->contains($product->id)) {
                throw new AppException(AppException::ERR_INVALID_QUERY);
            } else {
                $reqProd = $reqProducts->first(function ($p) use($product) {
                    if (isset($p['data'])) {
                        return $p['id'] === $product->id && $p['data'] == $product->pivot?->data['extra'];
                    } else {
                        return $p['id'] === $product->id;
                    }
                });

                if (is_null($reqProd) || in_array($reqProd['id'], $periodicIds) !== $cart->isProductInPeriodicIds($reqProd['id'])) {
                    throw new AppException(AppException::ERR_INVALID_QUERY);
                }

                if ($reqProd['quantity'] !== $product->pivot?->data['quantity']) {
                    throw new AppException(AppException::ERR_INVALID_QUERY);
                }
            }
        }

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param CartUpdateRequest $request
     * @param IECommerceUser $user
     * @param integer $currency
     *
     * @return mixed
     */
    public function updateCartDeliveryData(CartUpdateRequest $request, IECommerceUser $user, int $currency)
    {
        /** @var Cart $cart */
        $cart = $this->getPurchasingCart($user, $currency);

        $cart->setDeliveryAddress($request->getDeliveryAddressId());
        $cart->setDeliveryPreferredTimestamp($request->getDeliveryTimestamp());

        if (!is_null($request->getDeliveryAgentName()) && !is_null($request->getDeliveryAddressId())) {
            $agentClass = config('larapress.ecommerce.delivery_agents.' . $request->getDeliveryAgentName());
            if (!class_exists($agentClass)) {
                throw new AppException(AppException::ERR_OBJ_NOT_READY);
            }

            $address = $cart->getDeliveryAddress();
            /** @var IDeliveryAgentClient */
            $agent = new $agentClass();
            if ($agent->canDeliveryForAddress($address)) {
                $price = $agent->getEstimatedPrice($address, $currency);
                $cart->setDeliveryPrice($price);
                $cart->setDeliveryAgentName($request->getDeliveryAgentName());
            } else {
                throw new AppException(AppException::ERR_INVALID_PARAMS);
            }
        } else {
            $cart->setDeliveryPrice(0);
            $cart->setDeliveryAgentName(null);
        }

        /** @var IDeliveryAgent */
        $agent = app(IDeliveryAgent::class);
        $avAgents = $agent->getAvailableAgentsForCart($cart);
        $cart->setAvailableDeliveryAgents($avAgents);
        $cart->removeGiftCodeUsage();

        // update amount based on products and gift code
        $cart->amount = $this->cartService->calculateCartAmountFromDataAndProducts($cart);
        // save cart updates
        $cart->update();

        $this->resetPurchasingCache($user->id);
        $cart = $this->getPurchasingCart($user, $currency);
        CRUDUpdated::dispatch(
            Auth::user(),
            $cart,
            CartCRUDProvider::class,
            Carbon::now()
        );
        CartEvent::dispatch(
            $cart->id,
            Carbon::now()
        );

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param string $code
     * @param IECommerceUser $user
     * @param integer $currency
     *
     * @return Cart
     */
    public function updateCartGiftCodeData(string $code, IECommerceUser $user, int $currency)
    {
        /** @var Cart */
        $cart = $this->getPurchasingCart($user, $currency);
        $giftDetails = $this->giftService->getGiftUsageDetailsForCart(
            $user,
            $cart,
            $code,
        );

        if (!is_null($giftDetails)) {
            $cart->setGiftCodeUsage($giftDetails);
        }

        // update amount based on products and gift code
        $cart->amount = $this->cartService->calculateCartAmountFromDataAndProducts($cart);
        // save cart updates
        $cart->update();

        $this->resetPurchasingCache($user->id);
        $cart = $this->getPurchasingCart($user, $currency);
        CRUDUpdated::dispatch(
            Auth::user(),
            $cart,
            CartCRUDProvider::class,
            Carbon::now()
        );
        CartEvent::dispatch(
            $cart->id,
            Carbon::now()
        );

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param int $currency
     *
     * @return Cart
     */
    public function updatePurchasingCart(CartUpdateRequest $request, IECommerceUser $user, int $currency)
    {
        /** @var Cart $cart */
        $cart = $this->getPurchasingCart($user, $currency);

        $productsData = new Collection($request->getProducts());
        $products = Product::whereIn('id', $productsData->pluck('id'))->get();

        // check if periodics requested are periodic purchasable
        $periodicIds = $request->getPeriodicIds();
        foreach ($products as $product) {
            if (!$product->isPeriodicSaleAvailable() && in_array($product->id, $periodicIds)) {
                throw new AppException(AppException::ERR_INVALID_PARAMS);
            }
        }

        // update cart data
        $cart->products()->sync([]);
        $products = $products->keyBy('id');
        foreach ($productsData as $productData) {
            $product = $products->get($productData['id']);
            $itemPrice = in_array($product->id, $periodicIds) ? $product->pricePeriodic($currency) : $product->price($currency);
            $cart->products()->attach($product->id, [
                'data' => [
                    'amount' => $itemPrice,
                    'currency' => $cart->currency,
                    'quantity' => (isset($productData['quantity']) ? $productData['quantity'] : 1),
                    'extra' => (isset($productData['data']) ? $productData['data'] : []),
                ],
            ]);
        }
        $cart->setPeriodicProductIds($periodicIds);
        $cart->setUseBalance($request->getUseBalance());
        $cart->load('products');

        // check and update gift code usage
        $giftDetails = null;
        if (!is_null($request->getGiftCode())) {
            $giftDetails = $this->giftService->getGiftUsageDetailsForCart(
                $user,
                $cart,
                $request->getGiftCode()
            );
            if (!is_null($giftDetails)) {
                $cart->setGiftCodeUsage($giftDetails);
            }
        }

        // update amount based on products and gift code
        $cart->amount = $this->cartService->calculateCartAmountFromDataAndProducts($cart);
        // save cart updates
        $cart->update();

        $this->resetPurchasingCache($user->id);
        $cart = $this->getPurchasingCart($user, $currency);
        CRUDUpdated::dispatch(
            Auth::user(),
            $cart,
            CartCRUDProvider::class,
            Carbon::now()
        );
        CartEvent::dispatch(
            $cart->id,
            Carbon::now()
        );

        return $cart;
    }


    /**
     * Undocumented function
     *
     * @param Request $request
     * @param IECommerceUser $user
     * @param ICartItem $cartItem
     * @param int $currency
     *
     * @return ICart
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
                if ($existingProd->pivot->data['extra'] == $request->getExtraData()) {
                    $existingPivot = $existingProd;
                    if ($product->isQuantized()) {
                        $quantity += $existingProd->pivot->data['quantity'];
                    }
                    break;
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

        // if product is not quantized and already exists in cart, throw error
        if (!$product->isQuantized() && !is_null($existingPivot)) {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }

        $itemPrice = $product->price($cart->currency);
        // remove item children if already in the cart
        if (count($product->children)) {
            $childIds = $product->children->pluck('id');
            $cart->products()->detach($childIds);
        }

        if (!is_null($existingPivot)) {
            $existingPivot->pivot->delete();
        }
        // add product to cart with extra informations
        $cart->products()->attach($product->model(), [
            'data' => [
                'amount' => $itemPrice,
                'currency' => $cart->currency,
                'quantity' => $quantity,
                'extra' => $request->getExtraData(),
            ],
        ]);

        $cart->removeGiftCodeUsage();

        $cart->load('products');
        $cart->amount = $this->cartService->calculateCartAmountFromDataAndProducts($cart);
        $cart->update();

        $this->resetPurchasingCache($user->id);
        $cart = $this->getPurchasingCart($user, $currency);

        CRUDUpdated::dispatch(
            Auth::user(),
            $cart,
            CartCRUDProvider::class,
            Carbon::now()
        );
        CartEvent::dispatch(
            $cart->id,
            Carbon::now()
        );

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param Request $request
     * @param ICartItem $cartItem
     *
     * @return ICart
     */
    public function removeItemFromPurchasingCart(CartContentModifyRequest $request, IECommerceUser $user, ICartItem $product, int $currency)
    {
        /** @var Cart */
        $cart = $this->getPurchasingCart($user, $currency);

        $products = $cart->products()->get();
        $existingPivot = null;
        foreach ($products as $existingProd) {
            if ($product->id === $existingProd->id) {
                if ($existingProd->pivot->data['extra'] == $request->getExtraData()) {
                    $existingPivot = $existingProd;
                    break;
                }
            }
        }

        if (is_null($existingPivot)) {
            return $cart;         // this item does not exist in our users current cart
        }

        $existingPivot->pivot->delete();
        $cart->removeGiftCodeUsage();

        $cart->load('products');
        $cart->amount = $this->cartService->calculateCartAmountFromDataAndProducts($cart);
        $cart->update();

        $this->resetPurchasingCache($user->id);
        $cart = $this->getPurchasingCart($user, $currency);

        CRUDUpdated::dispatch(
            Auth::user(),
            $cart,
            CartCRUDProvider::class,
            Carbon::now()
        );
        CartEvent::dispatch(
            $cart->id,
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
     *
     * @return ICart
     */
    public function getPurchasingCart(IECommerceUser $user, int $currency)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.purchase-cart',
            ['purchasing-cart:' . $user->id],
            3600,
            false,
            function () use ($user, $currency) {
                $membershipDomain = $user->getMembershipDomainId();

                if (is_null($membershipDomain)) {
                    throw new AppException(AppException::ERR_USER_HAS_NO_DOMAIN);
                }

                $cart = Cart::query()
                    ->with(['products'])
                    ->where('customer_id', $user->id)
                    ->where('domain_id', $membershipDomain)
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
            }
        );
    }

    /**
     * Undocumented function
     *
     * @param int $userId
     * @return void
     */
    public function resetPurchasingCache($userId)
    {
        Helpers::forgetCachedValues(['purchasing-cart:' . $userId]);
    }
}
