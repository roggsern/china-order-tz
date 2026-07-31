<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ActivateAdminRequest;
use App\Http\Requests\Admin\AssignAdminRoleRequest;
use App\Http\Requests\Admin\DeactivateAdminRequest;
use App\Http\Requests\Admin\IndexAdminsRequest;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Requests\Admin\UpdateAdminRequest;
use App\Http\Resources\AdminResource;
use App\Models\Admin;
use App\Services\Admin\AdminManagementService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminManagementService $admins,
    ) {}

    public function index(IndexAdminsRequest $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) ($request->validated('per_page') ?? 20), 1), 100);

        return AdminResource::collection(
            $this->admins->paginate($request->validated(), $perPage),
        )->additional(['success' => true]);
    }

    public function store(StoreAdminRequest $request): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();
        $admin = $this->admins->create($request->validated(), $actor);

        return response()->json([
            'success' => true,
            'data' => new AdminResource($admin),
        ], 201);
    }

    public function show(Admin $admin): JsonResponse
    {
        $this->authorize(AdminPermissions::ADMINS_VIEW);

        return response()->json([
            'success' => true,
            'data' => new AdminResource($this->admins->show($admin)),
        ]);
    }

    public function update(UpdateAdminRequest $request, Admin $admin): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();
        $updated = $this->admins->update($admin, $request->validated(), $actor);

        return response()->json([
            'success' => true,
            'data' => new AdminResource($updated),
        ]);
    }

    public function activate(ActivateAdminRequest $request, Admin $admin): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();

        return response()->json([
            'success' => true,
            'data' => new AdminResource($this->admins->activate($admin, $actor)),
        ]);
    }

    public function deactivate(DeactivateAdminRequest $request, Admin $admin): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();

        return response()->json([
            'success' => true,
            'data' => new AdminResource($this->admins->deactivate($admin, $actor)),
        ]);
    }

    public function assignRole(AssignAdminRoleRequest $request, Admin $admin): JsonResponse
    {
        /** @var Admin $actor */
        $actor = $request->user();
        $updated = $this->admins->assignRole($admin, (string) $request->validated('role_id'), $actor);

        return response()->json([
            'success' => true,
            'data' => new AdminResource($updated),
        ]);
    }
}
