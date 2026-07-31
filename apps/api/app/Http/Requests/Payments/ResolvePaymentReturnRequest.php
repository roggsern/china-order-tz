<?php

namespace App\Http\Requests\Payments;

use Illuminate\Foundation\Http\FormRequest;

class ResolvePaymentReturnRequest extends FormRequest
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
            'order_id' => ['nullable', 'string', 'max:255'],
            'merchant_reference' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! filled($this->input('order_id')) && ! filled($this->input('merchant_reference'))) {
                $validator->errors()->add('order_id', 'An order id or merchant reference is required.');
            }
        });
    }
}
