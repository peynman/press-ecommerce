<?php


namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Cart\Base\CartInstallmentPaymentDetails;
use Larapress\ECommerce\Services\Cart\Base\CartInstallmentPurchaseDetails;
use Larapress\ECommerce\Services\Cart\Base\CartCustomInstallmentPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Events\CRUDUpdated;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\CRUD\CartCRUDProvider;
use Larapress\ECommerce\Services\Cart\Requests\CartInstallmentUpdateRequest;
use Larapress\ECommerce\Services\GiftCodes\IGiftCodeService;

class InstallmentCartService implements IInstallmentCartService
{
    /**
     * Undocumented function
     *
     * @return ICart[]
     */
    public function updateCartInstallmentsForPeriodicPurchases()
    {
        Cart::query()
            ->where('status', Cart::STATUS_ACCESS_COMPLETE)
            ->where('flags', '&', Cart::FLAGS_HAS_PERIODS)
            ->whereRaw('(flags & ' . Cart::FLAGS_PERIODIC_COMPLETED . ') = 0')
            ->chunk(100, function ($carts) {
                /** @var Cart[] $carts */
                foreach ($carts as $cart) {
                    $this->updateInstallmentsForCart($cart);
                }
            });
    }



    /**
     * Undocumented function
     *
     * @return ICart[]
     */
    public function updateCartInstallmentsForUser(IECommerceUser $user)
    {
        Cart::query()
            ->where('status', Cart::STATUS_ACCESS_COMPLETE)
            ->where('flags', '&', Cart::FLAGS_HAS_PERIODS)
            ->where('customer_id', $user->id)
            ->whereRaw('(flags & ' . Cart::FLAGS_PERIODIC_COMPLETED . ') = 0')
            ->chunk(100, function ($carts) {
                /** @var Cart[] $carts */
                foreach ($carts as $cart) {
                    $this->updateInstallmentsForCart($cart);
                }
            });
    }

    /**
     * Undocumented function
     *
     * @param ICart $cart
     *
     * @return void
     */
    public function updateInstallmentsForCart(ICart $cart)
    {
        if ($cart->isCustomPeriodicPayment()) {
            $this->updateInstallmentsForCartPeriodicCustom($cart->customer, $cart);
        } else {
            /** @var Product[] */
            $products = $cart->products;
            $allPeriodsCompleted = true;
            foreach ($products as $product) {
                $remainingPeriod = $this->updateSystemInstallmentsForProductInCart($cart->customer, $cart, $product);
                if ($remainingPeriod !== false) {
                    $allPeriodsCompleted = false;
                }
            }

            if ($allPeriodsCompleted) {
                /** @var Model $cart */
                $cart->update([
                    'flags' => $cart->flags | Cart::FLAGS_PERIODIC_COMPLETED,
                ]);

                CRUDUpdated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());
                CartEvent::dispatch(
                    $cart->id,
                    Carbon::now()
                );
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     *
     * @return ICart[]
     */
    public function updateSingleInstallmentsCarts(IECommerceUser $user)
    {
        $this->updateCartInstallmentsForUser($user);

        /** @var ICart[] */
        $installments = $this->getUserInstallments($user);

        $currencies = [];

        foreach ($installments as $installment) {
            if (!isset($currencies[$installment->currency])) {
                $currencies[$installment->currency] = [];
            }

            $currencies[$installment->currency][] = $installment;
        }

        $carts = [];
        foreach ($currencies as $currency => $installments) {

            $amount = 0;
            $ids = [];
            foreach ($installments as $installment) {
                $amount += $installment->amount;
                $ids = $installment->id;
            }

            /** @var Cart */
            $cart = Cart::updateOrCreate([
                'currency' => $currency,
                'customer_id' => $user->id,
                'domain_id' => $user->getMembershipDomainId(),
                'flags' => Cart::FLGAS_SINGLE_INSTALLMENT,
                'status' => Cart::STATUS_UNVERIFIED,
            ], [
                'amount' => $amount,
                'data' => [
                    'single_installment_carts' => $ids,
                ]
            ]);

            CRUDUpdated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());
            CartEvent::dispatch(
                $cart->id,
                Carbon::now()
            );

            $carts[] = $cart;
        }

        return $carts;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     *
     * @return ICart[]
     */
    public function getUserInstallments(IECommerceUser $user)
    {
        return Helpers::getCachedValue(
            'larapress.ecommerce.user.' . $user->id . '.',
            ['purchased-cart:' . $user->id],
            3600,
            true,
            function () use ($user) {
                return Cart::query()
                    ->with(['products'])
                    ->where('status', Cart::STATUS_UNVERIFIED)
                    ->where('flags', '&', Cart::FLAGS_PERIOD_PAYMENT_CART)
                    ->where('customer_id', $user->id)
                    ->get();
            }
        );
    }

    /**
     * Undocumented function
     *
     * @param int|Cart $originalCart
     * @param int|Product $product
     *
     * @return ICart|boolean
     */
    protected function updateSystemInstallmentsForProductInCart(IECommerceUser $user, ICart $originalCart, Product $product)
    {
        if (is_null($originalCart) || $originalCart->customer_id != $user->id) {
            return false;
        }

        if (!$originalCart->isProductInPeriodicIds($product)) {
            return false;
        }

        if ($originalCart->isPeriodicPaymentsCompletedOnProduct($product)) {
            return false;
        }

        $productPurchaseDetails = $originalCart->getPurchaseDetailsForProduct($product);
        $nextPeriod = $originalCart->getNextPeriodDueDateForProduct($product);

        $paymentDetails = new CartInstallmentPaymentDetails([
            'custom' => false,
            'product' => $product->id,
            'index' => $productPurchaseDetails->paidPeriods + 1,
            'total' => $productPurchaseDetails->periodsCount,
            'originalCart' => $originalCart->id,
            'due_date' => $nextPeriod->format(config('larapress.crud.datetime-format')),
        ]);

        /** @var Cart */
        $cart = Cart::updateOrCreate([
            'currency' => $originalCart->currency,
            'customer_id' => $user->id,
            'domain_id' => $originalCart->domain_id,
            'flags' => Cart::FLAGS_PERIOD_PAYMENT_CART,
            'status' => Cart::STATUS_UNVERIFIED,
            'data->periodic_pay->originalCart' => $originalCart->id,
            'data->periodic_pay->product' => $paymentDetails->product,
        ], [
            'amount' => $productPurchaseDetails->periodsPaymentAmount,
            'data' => [
                'periodic_pay' => $paymentDetails->toArray(),
            ]
        ]);

        CRUDUpdated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());
        CartEvent::dispatch(
            $cart->id,
            Carbon::now()
        );

        // detach all existing product/cart connections
        $cart->products()->sync([]);
        // save installment payment details
        $periodPurchaseDetails = new CartInstallmentPurchaseDetails($paymentDetails->toArray());
        $periodPurchaseDetails->amount = $productPurchaseDetails->periodsPaymentAmount;
        $cart->products()->attach($product->id, [
            'data' => $periodPurchaseDetails->toArray()
        ]);

        return $cart;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param Cart $originalCart
     *
     * @return ICart|boolean
     */
    protected function updateInstallmentsForCartPeriodicCustom(IECommerceUser $user, Cart $originalCart)
    {
        // cart is not for this user
        if (is_null($originalCart) || $originalCart->customer_id !== $user->id) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        // cart does not have custom periodic purchase info
        if (!$originalCart->isCustomPeriodicPayment()) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }

        $orderedPeriods = $originalCart->getCustomPeriodsOrdered();
        /** @var CartCustomInstallmentPeriod */
        $nextPeriod = $originalCart->getNextPeriodForCustomInstallments();

        if (is_null($nextPeriod)) {
            $originalCart->update([
                'flags' => $originalCart->flags | Cart::FLAGS_PERIODIC_COMPLETED,
            ]);
            return false;
        }

        /** @var Collection */
        $products = $originalCart->products;

        $paymentDetails = new CartInstallmentPaymentDetails([
            'custom' => true,
            'index' => $nextPeriod->index,
            'total' => count($orderedPeriods),
            'originalCart' => $originalCart->id,
            'due_date' => $nextPeriod->payment_at->format(config('larapress.crud.datetime-format')),
        ]);

        /** @var Cart */
        $cart = Cart::updateOrCreate([
            'currency' => $originalCart->currency,
            'customer_id' => $user->id,
            'domain_id' => $originalCart->domain_id,
            'flags' => Cart::FLAGS_PERIOD_PAYMENT_CART,
            'status' => Cart::STATUS_UNVERIFIED,
            'data->periodic_pay->originalCart' => $originalCart->id,
        ], [
            'amount' => $nextPeriod->amount,
            'data' => [
                'periodic_pay' => $paymentDetails->toArray(),
            ]
        ]);

        CRUDUpdated::dispatch(Auth::user(), $cart, CartCRUDProvider::class, Carbon::now());
        CartEvent::dispatch(
            $cart->id,
            Carbon::now()
        );

        $totalProductsInCart = $products->count();
        // detach all existing product/cart connections
        $cart->products()->sync([]);
        // save installment payment details
        foreach ($products as $product) {
            $purchaseDetails = new CartInstallmentPurchaseDetails($paymentDetails->toArray());
            $purchaseDetails->amount = $nextPeriod->amount / $totalProductsInCart;

            $cart->products()->attach($product->id, [
                'data' => $purchaseDetails->toArray()
            ]);
        }

        return $cart;
    }


    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param int|ICart $cart
     *
     * @return ICart
     */
    public function updatePeriodicPaymentCart(IECommerceUser $user, $cart, CartInstallmentUpdateRequest $request)
    {
        if (is_numeric($cart)) {
            /** @var Cart */
            $cart = Cart::find($cart);
        }

        if ($user->id !== $cart->customer_id) {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }

        $cart->setUseBalance($request->getUseBalance());
        // save cart updates
        /** @var Cart $cart */
        $cart->update();

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
}
