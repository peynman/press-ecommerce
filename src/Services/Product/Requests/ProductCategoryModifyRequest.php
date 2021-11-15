<?php

namespace Larapress\ECommerce\Services\Product\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Larapress\ECommerce\Models\Product;

class ProductCategoryModifyRequest extends FormRequest
{
    const MODE_REMOVE_CATEGORY = 'remove';
    const MODE_ADD_CATEGORY = 'add';
    const MODE_SYNC_CATEGORY = 'sync';

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
            'productIds.*' => 'required|numeric|exists:products,id',
            'mode' => 'required|string|in:'.implode(',', [
                self::MODE_ADD_CATEGORY,
                self::MODE_REMOVE_CATEGORY,
                self::MODE_SYNC_CATEGORY,
            ]),
            'categories.*' => 'required|numeric|exists:product_categories,id',
        ];
    }

    /**
     * Undocumented function
     *
     * @return int[]
     */
    public function getProductIds()
    {
        return $this->get('productIds', []);
    }

    /**
     * Undocumented function
     *
     * @return int[]
     */
    public function getCategoryIds()
    {
        return $this->get('categories', []);
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public function getMode()
    {
        return $this->get('mode', 'add');
    }
}
