<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturnRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'method' => ['sometimes', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Optional immediate status advance after create (still manual — not auto-complete).
            'status' => ['sometimes', 'string', 'in:pending,approved,processing,completed,failed'],
        ];
    }
}
