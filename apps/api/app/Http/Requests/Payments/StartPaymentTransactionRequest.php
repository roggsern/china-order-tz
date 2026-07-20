<?php

namespace App\Http\Requests\Payments;

use App\Enums\PaymentProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPaymentTransactionRequest extends FormRequest
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
            'provider' => ['sometimes', 'nullable', 'string', Rule::enum(PaymentProvider::class)],
        ];
    }
}
