<?php

namespace Larapress\ECommerce\Services\Cart\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 */
class CartInstallmentUpdateRequest extends FormRequest
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
}
