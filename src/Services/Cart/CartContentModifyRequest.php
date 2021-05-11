<?php

namespace Larapress\ECommerce\Services\Cart;

use Illuminate\Foundation\Http\FormRequest;
use Larapress\ECommerce\Models\Product;

/**
 * @bodyParam currency int required The currency to use this gift code on.
 * @bodyParam product_id int required The id of the product to add/remove in purchasing cart.
 * @bodyParam quantity int The quantity to add/remove.
 */
class CartContentModifyRequest extends FormRequest
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
            'currency' => 'required|numeric',
            'quantity' => 'nullable|numeric',
        ];
    }

    protected $product = null;
    /**
     * Undocumented function
     *
     * @return Product
     */
    public function getProduct()
    {
        if (is_null($this->product)) {
            $this->product = Product::find($this->get('product_id'));
        }

        return $this->product;
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getCurrency()
    {
        return $this->get('currency');
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getQuantity()
    {
        return $this->get('quantity', 1);
    }
}
