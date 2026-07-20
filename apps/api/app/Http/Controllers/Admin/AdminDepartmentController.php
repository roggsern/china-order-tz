<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminDepartments\CreateDepartmentAction;
use App\Actions\AdminDepartments\DeleteDepartmentAction;
use App\Actions\AdminDepartments\GetAdminDepartmentsAction;
use App\Actions\AdminDepartments\RestoreDepartmentAction;
use App\Actions\AdminDepartments\ShowDepartmentAction;
use App\Actions\AdminDepartments\UpdateDepartmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDepartmentRequest;
use App\Http\Requests\Admin\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminDepartmentController extends Controller
{
    public function index(GetAdminDepartmentsAction $action): AnonymousResourceCollection
    {
        return DepartmentResource::collection($action->handle())
            ->additional(['success' => true]);
    }

    public function store(StoreDepartmentRequest $request, CreateDepartmentAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new DepartmentResource($action->handle($request)),
        ], 201);
    }

    public function show(Department $department, ShowDepartmentAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new DepartmentResource($action->handle($department)),
        ]);
    }

    public function update(
        UpdateDepartmentRequest $request,
        Department $department,
        UpdateDepartmentAction $action,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => new DepartmentResource($action->handle($request, $department)),
        ]);
    }

    public function destroy(Department $department, DeleteDepartmentAction $action): JsonResponse
    {
        $action->handle($department);

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully',
        ]);
    }

    public function restore(string $id, RestoreDepartmentAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Department restored successfully.',
            'data' => new DepartmentResource($action->handle($id)),
        ]);
    }
}
