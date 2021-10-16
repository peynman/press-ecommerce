<?php

namespace Larapress\ECommerce\Services\Banking;

use Illuminate\Foundation\Http\FormRequest;

class BankRedirectRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return is_null($this->provider);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            // 'gatewayId' => 'required|numeric|exists:bank_gateways,id',
            // 'amount' => 'required_without:cart_id|numeric',
            // 'cartId' => 'required_without:amount|exists:carts,id',
            // 'currency' => 'required_without:cart_id|numeric',
            // 'successRedirect' => 'nullable|string',
            // 'failedRedirect' => 'nullable|string',
            // 'canceledRedirect' => 'nullable|string',
        ];
    }

    /**
     * Undocumented function
     *
     * @return string|null
     */
    public function getSuccessRedirect()
    {
        return $this->get('successRedirect');
    }

    /**
     * Undocumented function
     *
     * @return string|null
     */
    public function getFailedRedirect()
    {
        return $this->get('failedRedirect');
    }

    /**
     * Undocumented function
     *
     * @return string|null
     */
    public function getCanceledRedirect()
    {
        return $this->get('canceledRedirect');
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getCartId()
    {
        return $this->get('cartId');
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getGatewayId()
    {
        return $this->get('gatewayId');
    }

    /**
     * Undocumented function
     *
     * @return float
     */
    public function getAmount()
    {
        return floatVal($this->get('amount'));
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getCurrency()
    {
        return intVal($this->get('currency'));
    }
}
