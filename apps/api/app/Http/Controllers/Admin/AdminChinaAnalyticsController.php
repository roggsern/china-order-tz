<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\ChinaCommercialAnalyticsService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminChinaAnalyticsController extends Controller
{
    public function __construct(
        private readonly ChinaCommercialAnalyticsService $analytics,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->analytics->overview($this->filters($request)),
        ]);
    }

    public function landedCost(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->analytics->landedCost($this->filters($request)),
        ]);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->analytics->suppliers($this->filters($request)),
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->analytics->categories($this->filters($request)),
        ]);
    }

    public function shipments(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::ANALYTICS_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->analytics->shipments($this->filters($request)),
        ]);
    }

    /**
     * @return array{from?: string|null, to?: string|null, limit?: int|null}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $validated;
    }
}
