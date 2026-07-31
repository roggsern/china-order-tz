<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Settings\ConfigurationDashboardService;
use App\Services\Settings\SettingsAuditQueryService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsDashboardController extends Controller
{
    public function __construct(
        private readonly ConfigurationDashboardService $dashboard,
        private readonly SettingsAuditQueryService $auditHistory,
    ) {}

    public function dashboard(): JsonResponse
    {
        $this->authorize(AdminPermissions::SETTINGS_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->dashboard->dashboard(),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::SETTINGS_VIEW);

        $validated = $request->validate([
            'event' => ['sometimes', 'nullable', 'string'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $paginator = $this->auditHistory->paginate([
            'event' => $validated['event'] ?? null,
            'per_page' => $validated['per_page'] ?? 25,
            'page' => $validated['page'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'events' => $this->auditHistory->eventValues(),
            ],
        ]);
    }
}
