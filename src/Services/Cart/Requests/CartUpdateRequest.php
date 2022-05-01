<?php

namespace Larapress\ECommerce\Services\Cart\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Larapress\CRUD\Extend\ChainOfResponsibility;
use Larapress\ECommerce\Models\Product;
use Larapress\ECommerce\Services\Cart\CartPluginsChain;

/**
 * @bodyParam currency int required The currency to use this gift code on.
 * @bodyParam gateway int The gateway id to use when forwarding to payment gateway.
 * @bodyParam use_balance boolean Use existing balance or not.
 */
class CartUpdateRequest extends FormRequest
{
    protected $normalizedProducts;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $plugins = new CartPluginsChain();
        return $plugins->getCartUpdateRules([
            'currency' => 'required|numeric',
            'products.*.id' => 'nullable|exists:products,id',
            'products.*.quantity' => 'nullable|numeric',
            'products.*.data' => 'nullable|json_object',
            'gateway' => 'nullabel|exists:bank_gateways,id',
            'use_balance' => 'nullable|boolean',
        ]);
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getCurrency()
    {
        return $this->get('currency', config('larapress.ecommerce.banking.currency'));
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getGateway()
    {
        return $this->get('gateway', config('larapress.ecommerce.banking.default_gateway'));
    }

    /**
     * Undocumented function
     *
     * @return bool
     */
    public function getUseBalance()
    {
        return $this->get('use_balance', false);
    }

    /**
     * Undocumented function
     *
     * @return array|null
     */
    public function getProducts()
    {
        return $this->get('products', []);
    }

    /**
     * Undocumented function
     *
     * @return Product[]
     */
    public function getProductModels() {
        if (is_null($this->normalizedProducts)) {
            $productsData = new Collection($this->getProducts());
            $this->normalizedProducts = Product::whereIn('id', $productsData->pluck('id'))->get();
        }

        return $this->normalizedProducts;
    }
 }
