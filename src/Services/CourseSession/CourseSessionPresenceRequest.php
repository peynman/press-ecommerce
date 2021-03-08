<?php

namespace Larapress\ECommerce\Services\CourseSession;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @bodyParam duration integer required The amount of time a use was present in seconds.
 */
class CourseSessionPresenceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        // already handled in CRUD middleware
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
            'duration' => 'required|numeric',
        ];
    }

    public function getDuration()
    {
        return $this->get('duration');
    }
}
