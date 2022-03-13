<?php

namespace Larapress\ECommerce\Services\Cart;

use Closure;
use Illuminate\Http\Request;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Cart\Requests\CartValidateRequest;

interface ICartServicePlugin
{
    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function beforeContentModify(ICart $cart, Request $request, Closure $next);

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function afterContentModify(ICart $cart, Request $request, Closure $next);

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Closure $next
     *
     * @return mixed
     */
    public function beforePurchase(ICart $cart, Closure $next);

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Closure $next
     *
     * @return mixed
     */
    public function afterPurchase(ICart $cart, Closure $next);

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function validateBeforeBankForwarding(ICart $cart, Request $request, Closure $next);

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
        ICart $cart,
        array $requestProdPivot,
        Product $product,
        CartValidateRequest $request,
        Closure $next
    );

    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Closure $next
     * @return mixed
     */
    public function afterBankResolved(ICart $cart, Closure $next);
}
