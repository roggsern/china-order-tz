<?php

namespace App\Http\Resources;

use App\Models\StoreUserAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StoreUserAssignment */
class StoreUserAssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'admin_id' => $this->admin_id,
            'operational_scope' => $this->operational_scope?->value,
            'operational_scope_label' => $this->operational_scope?->label(),
            'assignment_type' => $this->assignment_type?->value,
            'is_active' => $this->is_active,
            'is_currently_active' => $this->isCurrentlyActive(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'assigned_by' => $this->assigned_by,
            'admin' => $this->whenLoaded('admin', fn () => new AdminSummaryResource($this->admin)),
            'assigned_by_admin' => $this->whenLoaded('assignedByAdmin', fn () => new AdminSummaryResource($this->assignedByAdmin)),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
