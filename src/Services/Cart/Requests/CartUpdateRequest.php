<?php

namespace Larapress\ECommerce\Services\Cart\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam currency int required The currency to use this gift code on.
 * @bodyParam gift_code string The gift code secret.
 * @bodyParam periods object[] The Product ids which are in periodic mode.
 * @bodyParam gateway int The gateway id to use when forwarding to payment gateway.
 * @bodyParam use_balance boolean Use existing balance or not.
 */
class CartUpdateRequest extends FormRequest
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
        return [
            'currency' => 'required|numeric',
            'products.*.id' => 'nullable|exists:products,id',
            'products.*.quantity' => 'nullable|numeric',
            'products.*.data' => 'nullable|json_object',
            'periods.*' => 'nullable|exists:products,id',
            'gateway' => 'nullabel|exists:bank_gateways,id',
            'gift_code' => 'nullable|exists:gift_codes,code',
            'use_balance' => 'nullable|boolean',
            'delivery_address' => 'nullable|numeric|exists:physical_addresses,id',
            'delivery_timestamp' => 'nullable|datetime_zoned',
            'delivery_agent' => 'nullable|string|in:'.implode(',', array_keys(config('larapress.ecommerce.delivery_agents'))),
        ];
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
