<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
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
            'order_id' => ['required', 'exists:orders,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'status' => ['required', Rule::enum(PaymentStatus::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'transaction_reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
        ];
    }
}
