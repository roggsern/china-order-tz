<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\China\Procurement\ChinaProcurementReconciliationService;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;

class AdminChinaProcurementReconciliationController extends Controller
{
    public function __construct(
        private readonly ChinaProcurementReconciliationService $reconciliation,
    ) {}

    public function show(): JsonResponse
    {
        $this->authorize(AdminPermissions::PROCUREMENT_VIEW);

        return response()->json([
            'success' => true,
            'data' => $this->reconciliation->report(),
        ]);
    }
}
