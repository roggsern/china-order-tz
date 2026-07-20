<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class IndexAdminNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'channel' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', 'string', 'max:50'],
            'event_type' => ['sometimes', 'string', 'max:100'],
            'customer_id' => ['sometimes', 'uuid'],
        ];
    }
}
