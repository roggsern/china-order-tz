<?php

namespace App\Http\Controllers;

use App\Actions\CustomerDashboard\ShowCustomerDashboardAction;
use App\Http\Resources\DashboardResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function show(ShowCustomerDashboardAction $action): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => new DashboardResource($action->handle($user)),
        ]);
    }
}
