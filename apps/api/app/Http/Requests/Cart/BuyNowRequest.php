<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

class BuyNowRequest extends FormRequest
{
    use ValidatesCartProductFields;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->cartProductRules();
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCartProductValidation();
    }

    public function withValidator($validator): void
    {
        $this->withCartVariantValidator($validator);
    }
}
