<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminDashboard\GetAdminDashboardAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminDashboardResource;
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
}
