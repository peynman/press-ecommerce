<?php

namespace Larapress\ECommerce\Services\SupportGroup;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;

class SupportGroupUpdateRequest extends FormRequest
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
            'user_ids.*' => 'required|exists:users,id',
            'support_user_id' => 'required_without:random_support_id|exists:users,id',
            'random_support_id' => 'nullable',
            'all_none_supp_users' => 'nullable',
        ];
    }

    public function getUserIds()
    {
        return $this->get('user_ids', []);
    }

    public function getSupportUserID()
    {
        return $this->get('support_user_id', null);
    }

    public function shouldRandomizeSupportIds()
    {
        return $this->get('random_support_id', false);
    }

    public function shouldUseAllNoneSupportUsers()
    {
        return $this->get('all_none_supp_users', false);
    }
}
