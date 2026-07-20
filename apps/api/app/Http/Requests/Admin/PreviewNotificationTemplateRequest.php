<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PreviewNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'variables' => ['sometimes', 'array'],
            'variables.*' => ['nullable'],
        ];
    }
}
