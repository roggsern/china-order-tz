<?php

namespace App\Http\Resources;

use App\Services\Admin\AdminRoleReadService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Role */
class RoleSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var AdminRoleReadService $reader */
        $reader = app(AdminRoleReadService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'users_count' => $reader->resolveUsersCount($this->resource),
            'permissions_count' => (int) ($this->permissions_count ?? $this->permissions()->count()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
