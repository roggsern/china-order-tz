<?php

namespace App\Http\Requests\Profile;

use App\Rules\E164PhoneNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
        $updatingNames = $this->filled('first_name') || $this->filled('last_name');

        return [
            'first_name' => [
                Rule::requiredIf($updatingNames),
                'nullable',
                'string',
                'max:255',
            ],
            'last_name' => [
                Rule::requiredIf($updatingNames),
                'nullable',
                'string',
                'max:255',
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20', new E164PhoneNumber()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $updatingNames = $this->filled('first_name') || $this->filled('last_name');
            $updatingPhone = $this->exists('phone');

            if (! $updatingNames && ! $updatingPhone) {
                $validator->errors()->add(
                    'first_name',
                    'Provide first_name and last_name, or phone, to update the profile.',
                );
            }
        });
    }
}
