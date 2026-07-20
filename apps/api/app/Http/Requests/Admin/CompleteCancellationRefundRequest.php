<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CompleteCancellationRefundRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reference' => ['required', 'string', 'max:191'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'confirm' => ['required', 'accepted'],
        ];
    }
}
