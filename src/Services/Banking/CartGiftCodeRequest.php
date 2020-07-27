<?php

namespace Larapress\ECommerce\Services\Banking;

use Illuminate\Foundation\Http\FormRequest;
use Larapress\ECommerce\Models\Product;

class CartGiftCodeRequest extends FormRequest
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
            'gift_code' => 'required|string|min:6',
        ];
    }

    public function getCurrency() {
        return $this->get('currency');
    }

    public function getGiftCode() {
        return $this->get('gift_code');
    }
}