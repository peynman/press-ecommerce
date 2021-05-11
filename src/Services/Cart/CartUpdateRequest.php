<?php

namespace Larapress\ECommerce\Services\Cart;

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
            'periods' => 'nullable',
            'periods.*' => 'exists:products,id',
            'gateway' => 'nullabel|exists:bank_gateways,id',
            'gift_code' => 'nullable|exists:gift_codes,code',
            'use_balance' => 'nullable|boolean',
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
    public function getGateway() {
        return $this->get('gateway', config('larapress.ecommerce.banking.default_gateway'));
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getGiftCode() {
        return $this->get('gift_code');
    }

    /**
     * Undocumented function
     *
     * @return bool
     */
    public function getUseBalance() {
        return $this->get('use_balance', false);
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function getPeriodicIds() {
        return $this->get('periods', []);
    }
}
