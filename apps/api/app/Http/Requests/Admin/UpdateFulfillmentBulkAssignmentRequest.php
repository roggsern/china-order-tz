<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Rules\EligibleFulfillmentAssignee;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFulfillmentBulkAssignmentRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    public const MAX_BATCH_SIZE = 50;

    protected function requiredPermission(): string
    {
        return AdminPermissions::ORDERS_FULFILL;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fulfillment_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH_SIZE],
            'fulfillment_ids.*' => ['required', 'distinct', 'uuid'],
            'assigned_to' => [
                'present',
                'nullable',
                'uuid',
                new EligibleFulfillmentAssignee,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fulfillment_ids.max' => 'You can assign at most '.self::MAX_BATCH_SIZE.' fulfillments at a time.',
        ];
    }
}
