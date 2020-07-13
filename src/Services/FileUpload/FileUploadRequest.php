<?php

namespace Larapress\ECommerce\Services\FileUpload;

use Illuminate\Foundation\Http\FormRequest;

class FileUploadRequest extends FormRequest
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
            'file' => 'required|file',
            'access' => 'required|in:public,private',
            'title' => 'nullable|string',
        ];
    }


    public function getAccess() {
        return $this->get('access');
    }

    public function getTitle() {
        return $this->get('title');
    }
}
