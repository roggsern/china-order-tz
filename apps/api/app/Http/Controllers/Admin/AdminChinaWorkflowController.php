<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChinaQcStatus;
use App\Enums\SupplierPoResponse;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Services\China\ChinaWorkflowEngine;
use App\Support\Admin\AdminPermissions;
use App\Support\China\ChinaWorkflowPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminChinaWorkflowController extends Controller
{
    public function __construct(
        private readonly ChinaWorkflowEngine $workflow,
    ) {}

    public function show(Order $order): JsonResponse
    {
        $this->authorize(AdminPermissions::PROCUREMENT_VIEW);

        $record = $this->workflow->showForOrder($order);
        $pos = PurchaseOrder::query()
            ->with(['supplier', 'items'])
            ->where('order_id', $order->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'workflow' => $record,
                'purchase_orders' => $pos,
            ],
        ]);
    }

    public function bootstrap(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        ChinaWorkflowPermissions::assert($admin, AdminPermissions::PROCUREMENT_CREATE, 'bootstrap');

        $order->loadMissing('fulfillment');
        if ($order->fulfillment === null) {
            return response()->json([
                'success' => false,
                'message' => 'Fulfillment must exist before China workflow bootstrap.',
            ], 422);
        }

        $record = $this->workflow->bootstrapFromFulfillment($order->fulfillment, $admin);

        return response()->json([
            'success' => true,
            'message' => 'China workflow bootstrapped.',
            'data' => $record,
        ]);
    }

    public function supplierResponse(PurchaseOrder $purchaseOrder, Request $request): JsonResponse
    {
        $admin = $this->admin();
        ChinaWorkflowPermissions::assert($admin, AdminPermissions::PROCUREMENT_UPDATE, 'supplier_response');

        $data = $request->validate([
            'response' => ['required', 'string', Rule::enum(SupplierPoResponse::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $po = $this->workflow->recordSupplierResponse(
            $purchaseOrder,
            SupplierPoResponse::from($data['response']),
            $data['notes'] ?? null,
            $admin,
        );

        return response()->json([
            'success' => true,
            'message' => 'Supplier response recorded.',
            'data' => $po,
        ]);
    }

    public function qc(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        ChinaWorkflowPermissions::assert($admin, AdminPermissions::PROCUREMENT_UPDATE, 'qc');

        $data = $request->validate([
            'status' => ['required', 'string', Rule::enum(ChinaQcStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'idempotency_key' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $record = $this->workflow->recordQc(
            $order,
            ChinaQcStatus::from($data['status']),
            $data['notes'] ?? null,
            $admin,
            $data['idempotency_key'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'QC recorded.',
            'data' => $record,
        ]);
    }

    public function consolidate(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        ChinaWorkflowPermissions::assert($admin, AdminPermissions::WAREHOUSE_JOBS_UPDATE, 'consolidate');

        $data = $request->validate([
            'batch' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $record = $this->workflow->completeConsolidation($order, $admin, $data['batch'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Consolidation completed.',
            'data' => $record,
        ]);
    }

    public function exportReady(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        ChinaWorkflowPermissions::assert($admin, AdminPermissions::WAREHOUSE_JOBS_COMPLETE, 'export_ready');

        $data = $request->validate([
            'commercial_invoice' => ['required', 'boolean'],
            'packing_list' => ['required', 'boolean'],
            'customs_docs' => ['required', 'boolean'],
            'weight_confirmed' => ['required', 'boolean'],
            'dimensions_confirmed' => ['required', 'boolean'],
            'carton_count' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $record = $this->workflow->markExportReady($order, $admin, $data);

        return response()->json([
            'success' => true,
            'message' => 'Export readiness approved.',
            'data' => $record,
        ]);
    }

    public function agentHandoff(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        ChinaWorkflowPermissions::assert($admin, AdminPermissions::ORDERS_SHIP, 'agent_handoff');

        $data = $request->validate([
            'agent_name' => ['required', 'string', 'max:191'],
            'agent_contact' => ['sometimes', 'nullable', 'string', 'max:191'],
            'evidence' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $record = $this->workflow->recordAgentHandoff(
            $order,
            $admin,
            $data['agent_name'],
            $data['agent_contact'] ?? null,
            $data['evidence'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Customer agent handoff recorded.',
            'data' => $record,
        ]);
    }

    private function admin(): Admin
    {
        $user = auth('sanctum')->user();
        if (! $user instanceof Admin) {
            abort(403);
        }

        $user->loadMissing('role');

        return $user;
    }
}
