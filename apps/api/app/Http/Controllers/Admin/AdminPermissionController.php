<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAdminPermissionsRequest;
use App\Http\Resources\PermissionResource;
use App\Services\Admin\AdminRoleReadService;
use App\Services\Admin\RolePermissionManagementService;
use Illuminate\Http\JsonResponse;

class AdminPermissionController extends Controller
{
    public function __construct(
        private readonly RolePermissionManagementService $permissions,
        private readonly AdminRoleReadService $roles,
    ) {}

    public function index(IndexAdminPermissionsRequest $request): JsonResponse
    {
        $permissions = $this->permissions->listCatalog();

        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => PermissionResource::collection($permissions),
                'permissions_by_domain' => $this->roles->groupPermissionsByDomain($permissions),
            ],
        ]);
    }
}
