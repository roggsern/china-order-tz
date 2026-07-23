<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminDashboard\GetAdminDashboardAction;
use App\Http\Controllers\Controller;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, GetAdminDashboardAction $action): JsonResponse
    {
        $this->authorize(AdminPermissions::REPORTS_VIEW);

        return response()->json([
            'success' => true,
            'data' => $action->handle(
                $request->query('from'),
                $request->query('to'),
            ),
        ]);
    }
}
