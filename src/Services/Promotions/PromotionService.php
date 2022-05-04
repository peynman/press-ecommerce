<?php

namespace Larapress\ECommerce\Services\Promotions;

use Illuminate\Support\Facades\DB;
use Larapress\ECommerce\Models\GiftCode;
use Larapress\ECommerce\IECommerceUser;
use Larapress\ECommerce\Models\GiftCodeUse;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Cart\Base\CartGiftDetails;
use Larapress\ECommerce\Services\Cart\ICart;

class PromotionService implements IPromotionService
{
    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param ICart $cart
     *
     * @return GiftCodeDetails[]
     */
    public function getAvailablePromotionsForCart(IECommerceUser $user, ICart $cart)
    {
        $giftCodeDetails = [];

        /** @var GiftCode[] */
        $promotions = GiftCode::query()
            ->where('flags', '&', GiftCode::FLAGS_PASSIVE)
            ->where(DB::raw('(flags & ' . GiftCode::FLAGS_EXPIRED . ')'), '=', 0)
            ->get();

        $items = $cart->products->sortBy(function ($p, $k) {
            return $p->price(config('larapress.ecommerce.banking.currency.id'));
        })->values()->all();
        $items1DList = [];

        foreach ($promotions as $code) {
            $whitelist_products = [];

            $restrict_products = isset($code->data['products']) && !is_null($code->data['products']) && count($code->data['products']) > 0;
            $resitrct_categories = isset($code->data['productCategories']) && !is_null($code->data['productCategories']) && count($code->data['productCategories']) > 0;
            if ($restrict_products) {
                $whitelist_products = $code->data['products'];
            }
            if ($resitrct_categories) {
                $catProducts = Product::whereHas('categories', function ($q) use ($code) {
                    return $q->whereIn('id', $code->data['productCategories']);
                })
                ->orWhereHas('categories.parent', function ($q) use ($code) {
                    return $q->whereIn('id', $code->data['productCategories']);
                })
                ->orWhereHas('categories.parent.parent', function ($q) use ($code) {
                    return $q->whereIn('id', $code->data['productCategories']);
                })
                ->orWhereHas('categories.parent.parent.parent', function ($q) use ($code) {
                    return $q->whereIn('id', $code->data['productCategories']);
                })
                ->select('id')->pluck('id')->toArray();
                $whitelist_products = array_merge($whitelist_products ?? [], $catProducts);
            }

            $minItemQuantityForPromotion = intval($code->data['min_items']);
            $totalQualifiedCount = 0;
            foreach ($items as $item) {
                if (in_array($item->id, $whitelist_products)) {
                    $itemQuantity = intval($item->pivot->data['quantity']);
                    $totalQualifiedCount += $itemQuantity;
                    for ($i = 0; $i < $itemQuantity; $i++) {
                        $items1DList[] = [$item->id, $item->price(config('larapress.ecommerce.banking.currency.id'))];
                    }
                }
            }

            if ($totalQualifiedCount >= $minItemQuantityForPromotion) {
                $offItemsCount = floor($totalQualifiedCount / $minItemQuantityForPromotion);

                for ($i = 0; $i < $offItemsCount; $i++) {
                    [$offItemId, $offItemAmount] = $items1DList[$i];
                    $giftCodeDetails[] = new CartGiftDetails([
                        'code_id' => $code->id,
                        'amount' => $offItemAmount,
                        'code' => $code->code,
                        'products' => [
                            $offItemId => $offItemAmount,
                        ],
                        'fixed_only' => false,
                        'restrict_products' => $offItemId,
                        'mode' => 'promotion:nbmb',
                    ]);
                }
            }
        }

        return $giftCodeDetails;
    }

    /**
     * Undocumented function
     *
     * @param IECommerceUser $user
     * @param ICart $cart
     * @param array $promotions
     *
     * @return ICart
     */
    public function markPromotionUsageForCart(IECommerceUser $user, ICart $cart, array $promotions): ICart
    {
        foreach ($promotions as $gift) {
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

        return $cart;
    }
}
