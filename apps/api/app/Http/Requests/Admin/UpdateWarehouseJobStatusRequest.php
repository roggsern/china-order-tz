<?php

namespace App\Http\Requests\Admin;

use App\Enums\WarehouseJobStatus;
use App\Models\Admin;
use App\Support\Admin\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseJobStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user instanceof Admin) {
            return false;
        }
        $status = (string) $this->input('status');
        $permission = $status === WarehouseJobStatus::ReadyToShip->value
            ? AdminPermissions::WAREHOUSE_JOBS_COMPLETE
            : AdminPermissions::WAREHOUSE_JOBS_UPDATE;

        return $user->hasAdminPermission($permission);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::enum(WarehouseJobStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
