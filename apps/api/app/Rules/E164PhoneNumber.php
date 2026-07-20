<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class E164PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        if (! preg_match('/^\+[1-9]\d{6,14}$/', $value)) {
            $fail('Enter a valid phone number in international format (e.g. +255712345678).');
        }
    }
}
