<?php

namespace Larapress\ECommerce\Services\Cart\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Larapress\CRUD\Extend\ChainOfResponsibility;
use Larapress\ECommerce\Services\Cart\CartPluginsChain;

class CartValidateRequest extends FormRequest
{
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
     * @return string|null
     */
    public function getGiftCode()
    {
        return $this->get('gift_code');
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
    public function getPeriodicIds()
    {
        return $this->get('periods', []);
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
     * @return int|null
     */
    public function getDeliveryAddressId()
    {
        return $this->get('delivery_address');
    }

    /**
     * Undocumented function
     *
     * @return Carbon|null
     */
    public function getDeliveryTimestamp()
    {
        if (!is_null($this->get('delivery_timestamp'))) {
            return Carbon::parse($this->get('delivery_timestamp'));
        }

        return null;
    }

    /**
     * Undocumented function
     *
     * @return string|null
     */
    public function getDeliveryAgentName () {
        return $this->get('delivery_agent');
    }
 }
