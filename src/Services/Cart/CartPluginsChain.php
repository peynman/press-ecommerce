<?php

namespace Larapress\ECommerce\Services\Cart;

use Illuminate\Http\Request;
use Larapress\CRUD\Extend\ChainOfResponsibility;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Cart\Requests\CartValidateRequest;

class CartPluginsChain
{
    protected $classes;
    protected $plugins;

    public function __construct() {
        $this->classes = config('larapress.ecommerce.carts.plugins');
        $this->plugins = [];
        foreach ($this->classes as $class) {
            $this->plugins[] = app('\\'.$class);
        }
    }

    /**
     * Undocumented function
     *
     * @param array $rules
     *
     * @return array
     */
    public function getCartUpdateRules(array $rules = []) {
        $chain = new ChainOfResponsibility($this->plugins);
        return $chain->aggregate('getCartModifyRules', function($rules) { return $rules; }, $rules);
    }


    /**
     * Undocumented function
     *
     * @param ICart $cart
     * @param Request $request
     *
     * @return mixed
     */
    public function validateBeforeBankForwarding(
        ICart $cart,
        Request $request,
    ) {
        $chain = new ChainOfResponsibility($this->plugins);
        return $chain->handle('validateBeforeBankForwarding', $cart, $request);
    }


    /**
     * Undocumented function
     *
     * @param ICart                 $cart
     * @param array                 $requestProdPivot
     * @param Product               $product
     * @param CartValidateRequest   $request
     *
     * @return mixed
     */
    public function validateProductDataInCart(
        ICart $cart,
        array $requestProdPivot,
        Product $product,
        CartValidateRequest $request,
    ) {
        $chain = new ChainOfResponsibility($this->plugins);
        return $chain->handle('validateProductDataInCart', $cart, $requestProdPivot, $product, $request);
    }
}
