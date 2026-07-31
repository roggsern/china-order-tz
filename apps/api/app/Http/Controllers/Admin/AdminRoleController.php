<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAdminRolesRequest;
use App\Http\Requests\Admin\PreviewRolePermissionsRequest;
use App\Http\Requests\Admin\ShowAdminRoleRequest;
use App\Http\Requests\Admin\UpdateRolePermissionsRequest;
use App\Http\Resources\RoleDetailResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\RoleSummaryResource;
use App\Models\Admin;
use App\Models\Role;
use App\Services\Admin\AdminManagementService;
use App\Services\Admin\AdminRoleReadService;
use App\Services\Admin\RolePermissionImpactService;
use App\Services\Admin\RolePermissionManagementService;
use Illuminate\Http\JsonResponse;

class AdminRoleController extends Controller
{
    public function __construct(
        private readonly AdminManagementService $admins,
        private readonly AdminRoleReadService $roles,
        private readonly RolePermissionManagementService $rolePermissions,
        private readonly RolePermissionImpactService $rolePermissionImpact,
    ) {}

    public function index(IndexAdminRolesRequest $request): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();

        if ($request->boolean('assignable')) {
            return response()->json([
                'success' => true,
                'data' => RoleResource::collection($this->admins->listAssignableRoles($actor)),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => RoleSummaryResource::collection($this->roles->list()),
        ]);
    }

    public function show(ShowAdminRoleRequest $request, Role $role): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new RoleDetailResource($this->roles->show($role)),
        ]);
    }

    public function previewPermissions(PreviewRolePermissionsRequest $request, Role $role): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->rolePermissionImpact->preview(
                $role,
                $request->validated('add') ?? [],
                $request->validated('remove') ?? [],
                $actor,
            ),
        ]);
    }

    public function updatePermissions(UpdateRolePermissionsRequest $request, Role $role): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();

        $updated = $this->rolePermissions->applyDiff(
            $role,
            $request->validated('add') ?? [],
            $request->validated('remove') ?? [],
            $actor,
        );

        return response()->json([
            'success' => true,
            'data' => new RoleDetailResource($this->roles->show($updated)),
        ]);
    }
}
