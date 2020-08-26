<?php

namespace Larapress\ECommerce\Services\SupportGroup;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;

class MySupportGroupUpdateRequest extends FormRequest
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
            'support_user_id' => 'required|exists:users,id',
        ];
    }

    public function getSupportUserID() {
        return $this->get('support_user_id', null);
    }
}
