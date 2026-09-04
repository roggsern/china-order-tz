<?php

namespace App\Http\Requests\Admin;

use App\Enums\CommerceChannelCode;
use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAdminOrdersRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::ORDERS_VIEW;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'status' => ['sometimes', 'nullable', 'string', 'max:64'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'commerce_channel' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in([
                    CommerceChannelCode::ChinaImport->value,
                    CommerceChannelCode::TzLocal->value,
                ]),
            ],
        ];
    }
}
