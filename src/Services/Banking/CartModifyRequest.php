<?php

namespace Larapress\ECommerce\Services\Banking;

use Illuminate\Foundation\Http\FormRequest;
use Larapress\ECommerce\Models\Product;

class CartModifyRequest extends FormRequest
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
            'product_id' => 'required|exists:products,id',
        ];
    }

    protected $product = null;
    public function getProduct() {
        if (is_null($this->product)) {
            $this->product = Product::find($this->get('product_id'));
        }

        return $this->product;
    }
}
