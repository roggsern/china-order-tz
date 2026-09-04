<?php

namespace App\Rules;

use App\Models\Admin;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EligibleFulfillmentAssignee implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $admin = Admin::query()->find((string) $value);
        if ($admin === null) {
            $fail('The selected admin does not exist.');

            return;
        }

        if (! $admin->is_active) {
            $fail('The selected admin is inactive.');

            return;
        }

        if (! $admin->isEligibleFulfillmentAssignee()) {
            $fail('The selected admin cannot own this fulfillment.');
        }
    }
}
