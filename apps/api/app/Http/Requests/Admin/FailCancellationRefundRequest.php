<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FailCancellationRefundRequest extends FormRequest
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
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
