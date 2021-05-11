<?php

namespace Larapress\ECommerce\Services\Product;

use Illuminate\Foundation\Http\FormRequest;
use Larapress\ECommerce\Models\Product;

/**
 * @bodyParam page int The page number in paginated query. Exaple: 1
 * @bodyParam limit int The number of records in each page. Example: 30
 * @bodyParam categories int[] Category ids to filter products on. Example: [1, 2]
 * @bodyParam types int[] Type ids to filter products on. Example: [1, 3]
 * @bodyParam purchased boolean Query only in purchased products. Example: false
 */
class ProductQueryRequest extends FormRequest
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
            'page' => 'nullable|numeric',
            'limit' => 'nullable|numeric|min:'.config('larapress.ecommerce.repository.min_limit').'|max:'.config('larapress.ecommerce.repository.min_limit'),
            'categories' => 'nullable|array',
            'categories.*' => 'numeric|exists:product_categories,id',
            'types' => 'nullable|array',
            'types.*' => 'numeric|exists:product_types,id',
            'purchased' => 'boolean',
        ];
    }

    public function showOnlyPurchased() {

    }
}
