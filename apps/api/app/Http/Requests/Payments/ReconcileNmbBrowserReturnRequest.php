<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class ReconcileNmbBrowserReturnRequest extends FormRequest
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
            'payment_transaction_id' => ['required', 'string', 'uuid'],
            'merchant_reference' => ['required', 'string', 'max:255'],
            'success_indicator' => ['required', 'string', 'max:255'],
            'result_indicator' => ['required', 'string', 'max:255'],
            'order_id' => ['nullable', 'string', 'uuid'],
        ];
    }
}
