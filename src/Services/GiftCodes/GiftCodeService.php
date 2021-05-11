<?php

namespace Larapress\ECommerce\Services\GiftCodes;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Larapress\CRUD\Events\CRUDCreated;
use Larapress\CRUD\Exceptions\AppException;
use Larapress\CRUD\Extend\Helpers;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\Cart;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\CRUD\BaseFlags;
use Larapress\ECommerce\Models\GiftCodeUse;
use Larapress\ECommerce\Services\Cart\CartGiftDetails;

class GiftCodeService implements IGiftCodeService
{
    /**
     * Undocumented function
     *
     * @param int|GiftCode $giftCode
     * @param int $count
     * @return array
     */
    public function cloneGiftCode($giftCode, $count)
    {
        /** @var GiftCode */
        if (is_numeric($giftCode)) {
            $giftCode = GiftCode::find($giftCode);
        }

        if (is_null($giftCode)) {
            throw new AppException(AppException::ERR_OBJECT_NOT_FOUND);
        }

        $data = $giftCode->toArray();
        unset($data['id']);

        $duplicates = [];
        for ($i = 0; $i < $count; $i++) {
            $data['code'] = Helpers::randomNumbers(8);
            /** @var GiftCode */
            $duplicate = GiftCode::create($data);
            CRUDCreated::dispatch(Auth::user(), $duplicate, GiftCodeCRUDProvider::class, Carbon::now());
            $duplicates[] = $duplicate;
        }

        return collect($duplicates)->pluck('code');
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param Cart $cart
     * @param string $code
     *
     * @return CartGiftDetails|null
     */
    public function getGiftUsageDetailsForCart(IECommerceUser $user, Cart $cart, $code)
    {
        /** @var GiftCode */
        $code = GiftCode::query()
            ->where('code', $code)
            ->where('status', GiftCode::STATUS_AVAILABLE)
            ->first();
        if (is_null($code)) {
            throw new AppException(AppException::ERR_INVALID_PARAMS);
        }
        if (isset($code->data['expires_at'])) {
            $expireAt = Carbon::parse($code->data['expires_at']);
            if (Carbon::now() > $expireAt) {
                throw new AppException(AppException::ERR_NOT_GIFT_EXPIRED);
            }
        }

        $multiUsePerUser = isset($code->data['multi_time_use']) && $code->data['multi_time_use'] ? true : false;
        $use_case_for_user = GiftCodeUse::where('code_id', $code->id)->where('user_id', $user->id)->first();
        if (!is_null($use_case_for_user) && !$multiUsePerUser) {
            throw new AppException(AppException::ERR_INVALID_PARAMS);
        }

        $avUsersIds = isset($code->data['specific_ids']) ? explode(",", $code->data['specific_ids']) : null;
        if (!is_null($avUsersIds) && !in_array($user->id, $avUsersIds)) {
            throw new AppException(AppException::ERR_INVALID_PARAMS);
        }

        if (BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART)) {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }

        // check for supportrole on gift code validation
        $suppUserId = isset($code->data['support_id']) ? $code->data['support_id'] : "";
        if (is_numeric($suppUserId)) {
            if ($user->getSupportUserId() != $suppUserId) {
                throw new AppException(AppException::ERR_GIFT_LOCKED_TO_SUPPORT);
            }
        }
        /** @var ICartItem[] $products */
        $products = $cart->products;
        $data = $cart->data;
        if (is_null($data)) {
            $data = [];
        }
        $data['periodic_product_ids'] = [];
        $amount = 0;

        if (isset($code->data['min_items']) && $code->data['min_items'] > 0) {
            if ($code->data['min_items'] > count($products)) {
                throw new AppException(AppException::ERR_NOT_ENOUGHT_ITEMS_IN_CART);
            }
        }

        foreach ($products as $prod) {
            if ($cart->isProductInPeriodicIds($prod)) {
                $itemPrice = $prod->pricePeriodic($cart->currency);
            } else {
                $itemPrice = $prod->price($cart->currency);
            }

            $amount += ($itemPrice );
        }

        if (isset($code->data['min_amount']) && $code->data['min_amount'] > 0) {
            if ($code->data['min_amount'] > $amount) {
                throw new AppException(AppException::ERR_NOT_ENOUGHT_AMOUNT_IN_CART);
            }
        }

        $fixed_only = isset($code->data['fixed_only']) && $code->data['fixed_only'];
        switch ($code->data['type']) {
            case 'percent': // percentage of cart amount gift
                $percent = floatval($code->data['value']) / 100.0;
                $offProductIds = [];
                if ($percent <= 1) { // valid gift percentage
                    if (isset($code->data['products'])) { // gift code has restrictions on product ids
                        $avCodeProducts = array_keys($code->data['products']); // availabel product ids for this gift code
                        $offAmount = 0;
                        foreach ($avCodeProducts as $avId) {
                            foreach ($products as $item) {
                                if ($item->id === $avId) { // this product is in cart and avaialbel for gift code
                                    if (!$fixed_only || !$cart->isProductInPeriodicIds($item)) { // is gift code for all || this product is not in the periodic list
                                        $itemPrice = $cart->isProductInPeriodicIds($item) ? $item->pricePeriodic($cart->currency) : $item->price($cart->currency);
                                        $itemPriceOff = floor($percent * $itemPrice);
                                        $offAmount += $itemPriceOff;
                                        $offProductIds[$item->id] = $itemPriceOff;
                                    }
                                }
                            }
                        }
                    } else { // no gift code usage restriction on product id
                        if ($fixed_only) { // gift code for fixed only purchases
                            $offAmount = 0;
                            foreach ($products as $item) {
                                if (!$cart->isProductInPeriodicIds($item)) {
                                    $itemPrice = $item->price();
                                    $itemPriceOff = floor($percent * $itemPrice);
                                    $offAmount += $itemPriceOff;
                                    $offProductIds[$item->id] = $itemPriceOff;
                                }
                            }
                        } else { // gift code for all purchases (periodic/fixed)
                            $offAmount = floor($amount * $percent);
                        }
                    }
                    if ($code->amount < $offAmount) {
                        $offAmountExtra = $offAmount - $code->amount;
                        $prodShareOff = $offAmountExtra / count($offProductIds);
                        $offAmount = $code->amount;
                        foreach ($offProductIds as $pId => $pOffAmount) {
                            $offProductIds[$pId] = $pOffAmount - $prodShareOff;
                        }
                    }
                    return new CartGiftDetails([
                        'code_id' => $code->id,
                        'amount' => $offAmount,
                        'code' => $code->code,
                        'products' => $offProductIds,
                        'fixed_only' => $fixed_only,
                        'percent' => $percent,
                    ]);
                }
            case 'amount': // fixed amount gift
                $offProductIds = [];
                if (isset($code->data['products'])) { // fixed amount gift on specific product ids
                    $avCodeProducts = array_keys($code->data['products']);
                    $offAmount = 0;
                    $offProductsCount = 0;
                    // find how many products can be gifted
                    foreach ($avCodeProducts as $avId) {
                        foreach ($products as $item) {
                            if ($item->id === $avId) {
                                $offProductsCount += 1;
                            }
                        }
                    }
                    // if there are any giftable products, devide gift between them
                    if ($offProductsCount > 0) {
                        $offAmount = floatval($code->data['value']);
                        foreach ($avCodeProducts as $avId) {
                            foreach ($products as $item) {
                                if ($item->id === $avId) {
                                    $offProductIds[$item->id] = $offAmount / $offProductsCount;
                                }
                            }
                        }
                    }
                } else { // fixed amount gift with no restrictions
                    $offAmount = floatval($code->data['value']);
                    $offProductsCount = count($products);
                    foreach ($products as $item) {
                        $offProductIds[$item->id] = $offAmount / $offProductsCount;
                    }
                }
                $offAmount = min($code->amount, $offAmount);
                return new CartGiftDetails([
                    'code_id' => $code->id,
                    'amount' => $offAmount,
                    'code' => $code->code,
                    'products' => $offProductIds,
                    'fixed_only' => $fixed_only,
                ]);
        }

        throw new AppException(AppException::ERR_OBJ_NOT_READY);
    }



    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param Cart $cart
     * @param GiftCode $gift
     * @return Cart
     */
    public function markGiftCodeUsageForCart(IECommerceUser $user, Cart $cart, GiftCode $gift)
    {
        GiftCodeUse::create([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'code_id' => $gift->id
        ]);

        if (isset($gift->data['one_time_use']) && $gift->data['one_time_use']) {
            $gift->update([
                'status' => GiftCode::STATUS_EXPIRED
            ]);
        }
    }
}
