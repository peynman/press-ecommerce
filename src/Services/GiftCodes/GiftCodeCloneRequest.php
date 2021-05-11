<?php

namespace Larapress\ECommerce\Services\GiftCodes;

use Illuminate\Foundation\Http\FormRequest;
use Larapress\ECommerce\Models\GiftCode;

/**
 * @bodyParam duplicate int Clone target gift code how many times? Example: 3
 * @bodyParam gift_code_id int required The target gift code to clone. Example: 1
 */
class GiftCodeCloneRequest extends FormRequest
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
            'gift_code_id' => 'required|numeric|exists:gift_codes,id',
        ];
    }

    /**
     * Undocumented function
     *
     * @return GiftCode
     */
    public function getGiftCode()
    {
        return GiftCode::find($this->get('gift_code_id'));
    }

    /**
     * Undocumented function
     *
     * @return int
     */
    public function getCloneCount() {
        return $this->get('duplicate', 1);
    }
}
