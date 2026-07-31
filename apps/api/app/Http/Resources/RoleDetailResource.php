<?php

namespace App\Http\Resources;

use App\Services\Admin\AdminRoleReadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Role */
class RoleDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AdminRoleReadService $reader */
        $reader = app(AdminRoleReadService::class);

        return [
            'role' => new RoleSummaryResource($this->resource),
            'assigned_admins' => AdminSummaryResource::collection($this->whenLoaded('admins')),
            'permissions_by_domain' => $this->when(
                $this->relationLoaded('permissions'),
                fn () => $reader->groupPermissionsByDomain($this->permissions),
            ),
        ];
    }
}
