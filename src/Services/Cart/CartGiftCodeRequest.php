<?php

namespace Larapress\ECommerce\Services\Cart;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam currency int required The currency to use this gift code on.
 * @bodyParam gift_code string required The gift code secret.
 */
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

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getCurrency()
    {
        return $this->get('currency');
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getGiftCode()
    {
        return $this->get('gift_code');
    }
}
