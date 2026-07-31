<?php

namespace App\Http\Requests\CustomerOrders;

use App\Enums\LastMileReceivingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReceivingMethodRequest extends FormRequest
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
            'receiving_method' => [
                'required',
                'string',
                Rule::in(array_map(
                    static fn (LastMileReceivingMethod $method) => $method->value,
                    LastMileReceivingMethod::cases(),
                )),
            ],
        ];
    }
}
