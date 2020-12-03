<?php

namespace Larapress\ECommerce\Services\Banking;

use Illuminate\Foundation\Http\FormRequest;
use Larapress\ECommerce\Models\Product;

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
            'gateway' => is_null(config('larapress.ecommerce.banking.default_gateway')) ? 'nullable' : 'required|exists:bank_gateways,id',
            'gift_code' => 'nullable|exists:gift_codes,code',
            'use_balance' => 'nullable|boolean',
        ];
    }

    public function getCurrency() {
        return $this->get('currency');
    }
}
