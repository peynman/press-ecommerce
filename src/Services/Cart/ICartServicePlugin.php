<?php

namespace Larapress\ECommerce\Services\Cart;

use Closure;
use Illuminate\Http\Request;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Cart\Requests\CartUpdateRequest;
use Larapress\ECommerce\Services\Cart\Requests\CartValidateRequest;

interface ICartServicePlugin
{
    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param CartUpdateRequest $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function beforeContentModify(
        Closure $next,
        ICart $cart,
        CartUpdateRequest $request,
    );

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param CartUpdateRequest $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function afterContentModify(
        Closure $next,
        ICart $cart,
        CartUpdateRequest $request,
    );

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Closure $next
     *
     * @return mixed
     */
    public function beforePurchase(
        Closure $next,
        ICart $cart,
    );

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Closure $next
     *
     * @return mixed
     */
    public function afterPurchase(
        Closure $next,
        ICart $cart,
    );

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function validateBeforeBankForwarding(
        Closure $next,
        ICart $cart,
        Request $request,
    );

    /**
     * Undocumented function
     *
     * @param ICart                 $cart
     * @param array                 $requestProdPivot
     * @param Product               $product
     * @param CartValidateRequest   $request
     * @param Closure               $next
     *
     * @return mixed
     */
    public function validateProductDataInCart(
        Closure $next,
        ICart $cart,
        array $requestProdPivot,
        Product $product,
        CartValidateRequest $request,
    );

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Closure $next
     *
     * @return mixed
     */
    public function afterBankResolved(
        Closure $next,
        ICart $cart,
    );

    /**
     * Undocumented function
     *
     * @return mixed
     */
    public function getCartModifyRules(
        Closure $next,
        array $existingRules,
    );
}
