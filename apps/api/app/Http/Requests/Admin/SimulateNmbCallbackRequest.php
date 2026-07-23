<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\AuthorizesAdminPermission;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimulateNmbCallbackRequest extends FormRequest
{
    use AuthorizesAdminPermission;

    protected function requiredPermission(): string
    {
        return AdminPermissions::PAYMENTS_MANAGE_MANUAL;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'result' => ['required', 'string', Rule::in(['success', 'failed'])],
        ];
    }
}
