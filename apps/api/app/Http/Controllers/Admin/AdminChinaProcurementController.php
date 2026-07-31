<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChinaProcurementRequirementStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChinaProcurementRequirementResource;
use App\Models\Admin;
use App\Models\ChinaProcurementRequirement;
use App\Services\China\Procurement\ChinaProcurementBoardEngine;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminChinaProcurementController extends Controller
{
    public function __construct(
        private readonly ChinaProcurementBoardEngine $board,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::PROCUREMENT_VIEW);

        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(ChinaProcurementRequirementStatus::values())],
            'supplier_id' => ['sometimes', 'nullable', 'uuid', 'exists:suppliers,id'],
            'product_id' => ['sometimes', 'nullable', 'uuid', 'exists:products,id'],
            'product_variant_id' => ['sometimes', 'nullable', 'uuid', 'exists:product_variants,id'],
            'category_id' => ['sometimes', 'nullable', 'uuid', 'exists:categories,id'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->board->paginate($validated);

        return response()->json([
            'success' => true,
            'data' => ChinaProcurementRequirementResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, ChinaProcurementRequirement $requirement): JsonResponse
    {
        $this->authorize(AdminPermissions::PROCUREMENT_VIEW);

        return response()->json([
            'success' => true,
            'data' => new ChinaProcurementRequirementResource($this->board->show($requirement)),
        ]);
    }

    public function markPurchased(Request $request, ChinaProcurementRequirement $requirement): JsonResponse
    {
        $this->authorize(AdminPermissions::PROCUREMENT_UPDATE);

        /** @var Admin $admin */
        $admin = $request->user();

        $data = $request->validate([
            'quantity_purchased' => ['required', 'integer', 'min:1'],
        ]);

        $updated = $this->board->markPurchased($admin, $requirement, (int) $data['quantity_purchased']);

        return response()->json([
            'success' => true,
            'message' => 'Purchase quantity recorded.',
            'data' => new ChinaProcurementRequirementResource($updated),
        ]);
    }

    public function startQc(Request $request, ChinaProcurementRequirement $requirement): JsonResponse
    {
        $this->authorize(AdminPermissions::PROCUREMENT_UPDATE);

        /** @var Admin $admin */
        $admin = $request->user();

        $updated = $this->board->startQc($admin, $requirement);

        return response()->json([
            'success' => true,
            'message' => 'QC started for linked orders.',
            'data' => new ChinaProcurementRequirementResource($updated),
        ]);
    }

    public function complete(Request $request, ChinaProcurementRequirement $requirement): JsonResponse
    {
        $this->authorize(AdminPermissions::PROCUREMENT_UPDATE);

        /** @var Admin $admin */
        $admin = $request->user();

        $updated = $this->board->complete($admin, $requirement);

        return response()->json([
            'success' => true,
            'message' => 'Procurement requirement completed.',
            'data' => new ChinaProcurementRequirementResource($updated),
        ]);
    }
}
