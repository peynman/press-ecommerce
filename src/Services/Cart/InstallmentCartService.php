<?php


namespace Larapress\ECommerce\Services\Cart;

use Carbon\Carbon;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\Product;

class InstallmentCartService implements IInstallmentCartService
{

    /**
     * Undocumented function
     *
     * @return Cart[]
     */
    public function updateCartInstallmentsForPeriodicPurchases()
    {
        Cart::query()
            ->where('status', Cart::STATUS_ACCESS_COMPLETE)
            ->where('flags', '&', Cart::FLAGS_HAS_PERIODS)
            ->whereRaw('(flags & ' . Cart::FLAGS_PERIODIC_COMPLETED . ') = 0')
            ->chunk(100, function ($carts) {
                /** @var ICart[] $carts */
                foreach ($carts as $cart) {
                    if ($cart->isSystemPeriodicPayment()) {
                        $cart->onEachPeriodicProductId(function ($pid) use ($cart) {
                            $product = Product::find($pid);
                            if (!is_null($product)) {
                                $this->updateInstallmentsForProductInCart($cart->customer, $cart, $product);
                            }
                        });
                    } else {
                        $this->getInstallmentsForCartPeriodicCustom($cart->customer, $cart);
                    }
                }
            });
    }

    /**
     * Undocumented function
     *
     * @param int|Cart $originalCart
     * @param int|Product $product
     * @return Cart
     */
    public function updateInstallmentsForProductInCart(IECommerceUser $user, $originalCart, $product)
    {
        if (is_numeric($originalCart)) {
            /** @var Cart */
            $originalCart = Cart::find($originalCart);
        }
        if (is_null($originalCart) || $originalCart->customer_id != $user->id) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }
        if (is_numeric($product)) {
            $product = Product::find($product);
        }
        if (is_null($product)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }
        if (! $originalCart->isProductInPeriodicIds($product)) {
            return;
        }
        if ($originalCart->isPeriodicPaymentsCompletedOnProduct($product)) {
            return;
        }


        $calc = $product->data['calucalte_periodic'];
        $count = $calc['period_count'];
        $alreadyPaidCount = isset($alreadyPaidPeriods[$product->id]) ? count($alreadyPaidPeriods[$product->id]) : 0;
        if ($alreadyPaidCount >= $count) {
            return null;
        }
        $period_start = Carbon::parse($originalCart->data['period_start']);
        $duration = isset($calc['period_duration']) ? intval($calc['period_duration']) : 30;
        $total = isset($calc['period_count']) ? intval($calc['period_count']) : 1;
        if (isset($calc['ends_at']) && !is_null($calc['ends_at'])) {
            $endAt = Carbon::parse($calc['ends_at']);
            $remaingDays = $period_start->diffInDays($endAt);
            if ($remaingDays < $duration * $total) {
                $duration = floor($remaingDays / $total);
            }
        }
        $due_date = $period_start->addDays($duration * ($alreadyPaidCount + 1));

        $amount = $calc['period_amount'];
        if (isset($originalCart->data['gift_code']['percent'])) {
            $gifted_products = isset($originalCart->data['gift_code']['products']) ? $originalCart->data['gift_code']['products'] : [];
            if (in_array($product->id, $gifted_products) || count($gifted_products) === 0) {
                $percent = floatval($originalCart->data['gift_code']['percent']);
                $amount = ceil((1 - $percent) * $amount);
            }
        }

        /** @var Cart */
        $cart = Cart::updateOrCreate([
            'currency' => $originalCart->currency,
            'customer_id' => $user->id,
            'domain_id' => $originalCart->domain_id,
            'flags' => Cart::FLAGS_PERIOD_PAYMENT_CART,
            'status' => Cart::STATUS_UNVERIFIED,
            'data->periodic_pay->product->id' => $product->id,
        ], [
            'amount' => $amount,
            'data' => [
                'periodic_pay' => [
                    'index' => $alreadyPaidCount + 1,
                    'total' => $count,
                    'product' => [
                        'id' => $product->id,
                        'title' => $product->data['title'],
                    ],
                    'originalCart' => $originalCart->id,
                    'due_date' => $due_date,
                ],
            ]
        ]);

        return $cart;
    }

    /**
     *
     */
    public function updateInstallmentsForCartPeriodicCustom(IECommerceUser $user, $originalCart)
    {
        if (is_numeric($originalCart)) {
            $originalCart = Cart::find($originalCart);
        }

        if (is_null($originalCart) || $originalCart->customer_id != $user->id) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        // cart does not have periodic purchase custom info
        if (!isset($originalCart->data['periodic_custom']) || count($originalCart->data['periodic_custom']) === 0) {
            throw new AppException(AppException::ERR_OBJ_NOT_READY);
        }

        $periodConfig = array_map(function ($data) {
            $data['payment_at'] = Carbon::parse($data['payment_at']);
            return $data;
        }, array_filter($originalCart->data['periodic_custom'], function ($data) {
            return isset($data['payment_at']) && !is_null($data['payment_at']);
        }));
        usort($periodConfig, function ($a, $b) {
            return $a['payment_at']->getTimestamp() - $b['payment_at']->getTimestamp();
        });
        $payment_index = -1;
        $paymentInfo = null;
        $totalPeriods = count($periodConfig);
        $indexer = 0;
        foreach ($periodConfig as $custom) {
            if (isset($custom['status']) && $custom['status'] == 2) {
                $payment_index = $indexer;
                $paymentInfo = $custom;
                break;
            }
            $indexer++;
        }

        if ($payment_index >= 0 && !is_null($paymentInfo) && isset($paymentInfo['amount']) && isset($paymentInfo['payment_at'])) {
            /** @var Cart */
            $cart = Cart::updateOrCreate([
                'currency' => $originalCart->currency,
                'customer_id' => $user->id,
                'domain_id' => $originalCart->domain_id,
                'flags' => Cart::FLAGS_PERIOD_PAYMENT_CART,
                'status' => Cart::STATUS_UNVERIFIED,
                'data->periodic_pay->originalCart' => $originalCart->id,
            ], [
                'amount' => $paymentInfo['amount'],
                'data' => [
                    'periodic_pay' => [
                        'custom' => true,
                        'index' => $payment_index,
                        'total' => $totalPeriods,
                        'originalCart' => $originalCart->id,
                        'due_date' => $paymentInfo['payment_at'],
                        'product_titles' => $originalCart->products->map(function ($item) {
                            return $item->data['title'];
                        })
                    ],
                ]
            ]);
            return $cart;
        }

        return null;
    }
}
