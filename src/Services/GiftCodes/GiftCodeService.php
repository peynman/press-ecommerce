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
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Cart\Base\CartGiftDetails;

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
            ->whereRaw('(flags & '.(GiftCode::FLAGS_EXPIRED|GiftCode::FLAGS_PASSIVE).') = 0')
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

        $avUsersIds = isset($code->data['customers']) ? explode(",", $code->data['customers']) : null;
        if (!is_null($avUsersIds) && !in_array($user->id, $avUsersIds)) {
            throw new AppException(AppException::ERR_INVALID_PARAMS);
        }

        if (BaseFlags::isActive($cart->flags, Cart::FLAGS_PERIOD_PAYMENT_CART)) {
            throw new AppException(AppException::ERR_INVALID_QUERY);
        }

        /** @var ICartItem[] $products */
        $products = $cart->products;
        $amount = 0;

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
        $restrict_products = isset($code->data['products']) && !is_null($code->data['products']) && count($code->data['products']) > 0;
        $resitrct_categories = isset($code->data['productCategories']) && !is_null($code->data['productCategories']) && count($code->data['productCategories']) > 0;
        $whitelist_products = [];
        $offProductsCount = 0;


        if (isset($code->data['min_items']) && $code->data['min_items'] > 0) {
            if ($code->data['min_items'] > count($products)) {
                throw new AppException(AppException::ERR_NOT_ENOUGHT_ITEMS_IN_CART);
            }
        }

        // find list of whitelisted product ids based on id or category id
        if ($restrict_products) {
            $whitelist_products = $code->data['products'];
        }
        if ($resitrct_categories) {
            $cats = Product::whereHas('categories', function($q) use($code) {
                return $q->whereIn('id', $code->data['productCategories']);
            })->select('id')->get('id');
            $whitelist_products = array_merge($whitelist_products ?? [], $cats);
        }
        if (count($whitelist_products) > 0) {
            // find how many products can be gifted
            foreach ($products as $item) {
                if (in_array($item->id, $whitelist_products)) {
                    $offProductsCount += 1;
                }
            }
        } else {
            $offProductsCount = count($cart->products);
        }

        // fixed amount discount
        if ($code->data['gift_fix_amount']) {
            $offProductIds = [];
            $offAmount = 0;
            // if there are any giftable products, devide gift between them
            if ($offProductsCount > 0) {
                $offAmount = floatval($code->amount);
                foreach ($products as $item) {
                    if (in_array($item->id, $whitelist_products)) {
                        $offProductIds[$item->id] = $offAmount / $offProductsCount;
                    }
                }
            }

            return new CartGiftDetails([
                'code_id' => $code->id,
                'amount' => $offAmount,
                'code' => $code->code,
                'products' => $offProductIds,
                'fixed_only' => $fixed_only,
                'restrict_products' => $restrict_products,
            ]);
        }

        $percent = floatval($code->data['value']) / 100.0;
        $offProductIds = [];
        if ($percent <= 1) { // valid gift percentage
            $offAmount = 0;
            foreach ($products as $item) {
                if (count($whitelist_products) > 0 && !in_array($item->id, $whitelist_products)) {
                    continue; // prod not in whitelist of this gift code
                }
                if ($fixed_only && $cart->isProductInPeriodicIds($item)) {
                    continue; // is gift code for all || this product is not in the periodic list
                }

                $itemPrice = $cart->isProductInPeriodicIds($item) ? $item->pricePeriodic($cart->currency) : $item->price($cart->currency);
                $itemPriceOff = floor($percent * $itemPrice);
                $offAmount += $itemPriceOff;
                $offProductIds[$item->id] = $itemPriceOff;
            }

            // if we exceed the maximum amount of gift allowed, then return extra gift amount equally between products
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
                'restrict_products' => $restrict_products,
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

        if (isset($gift->data['expire_on_use_count']) && intval($gift->data['expire_on_use_count']) > 0) {
            $count = GiftCodeUse::where('code_id', $gift->id)->count();
            if ($count >= intval($gift->data['expire_on_use_count'])) {
                $gift->update([
                    'flags' => GiftCode::FLAGS_EXPIRED,
                ]);
            }
        }
    }
}
