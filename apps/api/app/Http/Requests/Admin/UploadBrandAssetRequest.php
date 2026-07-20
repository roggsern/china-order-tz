<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadBrandAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'field' => ['required', 'string', Rule::in(['logo', 'banner'])],
            'file' => ['required', 'file', 'image', 'max:5120'],
        ];
    }
}
