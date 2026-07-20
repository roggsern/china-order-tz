<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class ShowProductConfigurationRequest extends FormRequest
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
            'selections' => ['sometimes', 'array'],
            'selections.*' => ['uuid'],
        ];
    }
}
