<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Features\MaintenanceModeResolver;
use Illuminate\Http\JsonResponse;

/**
 * Public maintenance probe — safe for storefront clients (no settings internals).
 */
class StorefrontMaintenanceController extends Controller
{
    public function __construct(
        private readonly MaintenanceModeResolver $maintenance,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->maintenance->publicStatus(),
        ]);
    }
}
