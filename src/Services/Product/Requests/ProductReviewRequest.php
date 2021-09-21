<?php

namespace Larapress\ECommerce\Services\Product\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Larapress\ECommerce\Models\Product;

/**
 */
class ProductReviewRequest extends FormRequest
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
            'product_id' => 'required|numeric|exists:products,id',
            'stars' => 'nullable|numeric',
            'review' => 'nullable|string',
            'reaction' => 'nullable|string',
            'data' => 'nullable|json_object',
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
     * @return string|null
     */
    public function getReviewMessage()
    {
        return $this->get('review');
    }

    /**
     * Undocumented function
     *
     * @return int|null
     */
    public function getReviewStars()
    {
        return $this->get('stars');
    }

    /**
     * Undocumented function
     *
     * @return string|null
     */
    public function getReviewReaction()
    {
        return $this->get('reaction');
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getReviewData() {
        return $this->get('data', []);
    }
}
