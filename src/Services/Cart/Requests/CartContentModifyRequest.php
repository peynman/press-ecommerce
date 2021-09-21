<?php

namespace Larapress\ECommerce\Services\Cart\Requests;

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
            'productId' => 'required|exists:products,id',
            'currency' => 'nullable|numeric',
            'quantity' => 'nullable|numeric',
            'data' => 'nullable|json_object',
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
            $this->product = Product::find($this->get('productId'));
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
        return $this->get('currency', config('larapress.ecommerce.banking.currency.id'));
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

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getExtraData() {
        return $this->get('data', []);
    }
}
