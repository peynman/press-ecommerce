<?php

namespace Larapress\ECommerce\Services;

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
            'gateway' => 'required|exists:bank_gateways,id'
        ];
    }

    public function getCurrency() {
        return $this->get('currency');
    }
}
