<?php

namespace Larapress\ECommerce\Services\Product\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Larapress\ECommerce\Models\Product;

/**
 * @bodyParam duplicate int Clone target product how many times? Example: 3
 * @bodyParam product_id int required The target prouct id to clone. Example: 1
 */
class ProductCloneRequest extends FormRequest
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
            'duplicate' => 'nullable|numeric',
            'product_id' => 'required|numeric|exists:products,id',
        ];
    }

    /**
     * Undocumented function
     *
     * @return Product
     */
    public function getProduct()
    {
        return Product::find($this->get('product_id'));
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getProductID()
    {
        return $this->get('product_id');
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getCloneCount()
    {
        return $this->get('duplicate', 1);
    }
}
