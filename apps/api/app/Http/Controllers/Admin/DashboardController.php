<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminDashboard\GetAdminDashboardAction;
use App\Actions\AdminDashboard\ShowOperationsDashboardAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminDashboardResource;
use App\Http\Resources\OperationsDashboardResource;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(GetAdminDashboardAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new AdminDashboardResource($action->handle()),
        ]);
    }

    public function operations(ShowOperationsDashboardAction $action): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new OperationsDashboardResource($action->handle()),
        ]);
    }
}
