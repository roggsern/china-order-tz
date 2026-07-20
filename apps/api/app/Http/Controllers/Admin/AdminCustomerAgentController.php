<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WarehouseReleaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Order;
use App\Services\CustomerAgent\CustomerAgentWorkflowEngine;
use App\Support\CustomerAgent\CustomerAgentPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCustomerAgentController extends Controller
{
    public function __construct(
        private readonly CustomerAgentWorkflowEngine $workflow,
    ) {}

    public function show(Order $order): JsonResponse
    {
        $this->admin();
        $pickup = $this->workflow->showForOrder($order);

        return response()->json([
            'success' => true,
            'data' => $pickup,
            'tracking' => $this->workflow->trackingPayload($order),
        ]);
    }

    public function bootstrap(Order $order): JsonResponse
    {
        $admin = $this->admin();
        CustomerAgentPermissions::assert($admin, CustomerAgentPermissions::LOGISTICS, 'bootstrap');

        $pickup = $this->workflow->bootstrap($order, $admin);

        return response()->json([
            'success' => true,
            'message' => 'Customer Agent pickup bootstrapped.',
            'data' => $pickup,
        ]);
    }

    public function authorizePickup(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        CustomerAgentPermissions::assert($admin, CustomerAgentPermissions::LOGISTICS, 'authorize');

        $data = $request->validate([
            'expires_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'agent_company' => ['sometimes', 'nullable', 'string', 'max:191'],
            'agent_phone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'agent_email' => ['sometimes', 'nullable', 'email', 'max:191'],
            'reissue' => ['sometimes', 'boolean'],
        ]);

        $pickup = $this->workflow->authorize($order, $admin, $data);

        return response()->json([
            'success' => true,
            'message' => 'Pickup authorization issued.',
            'data' => $pickup,
        ]);
    }

    public function reject(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        CustomerAgentPermissions::assert($admin, CustomerAgentPermissions::LOGISTICS, 'reject');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $pickup = $this->workflow->rejectAuthorization($order, $admin, $data['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Pickup authorization rejected.',
            'data' => $pickup,
        ]);
    }

    public function revoke(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        CustomerAgentPermissions::assert($admin, CustomerAgentPermissions::LOGISTICS, 'revoke');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $pickup = $this->workflow->revokeAuthorization($order, $admin, $data['reason']);

        return response()->json([
            'success' => true,
            'message' => 'Pickup authorization revoked.',
            'data' => $pickup,
        ]);
    }

    public function schedule(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        CustomerAgentPermissions::assert($admin, CustomerAgentPermissions::WAREHOUSE, 'schedule');

        $data = $request->validate([
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $scheduledAt = isset($data['scheduled_at']) ? new \DateTimeImmutable($data['scheduled_at']) : null;
        $pickup = $this->workflow->schedulePickup($order, $admin, $scheduledAt, $data['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Pickup scheduled.',
            'data' => $pickup,
        ]);
    }

    public function release(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        CustomerAgentPermissions::assert($admin, CustomerAgentPermissions::WAREHOUSE, 'warehouse_release');

        $data = $request->validate([
            'status' => ['required', 'string'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $status = WarehouseReleaseStatus::tryFrom($data['status']);
        if ($status === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid warehouse release status.',
            ], 422);
        }

        $pickup = $this->workflow->transitionWarehouseRelease(
            $order,
            $admin,
            $status,
            $data['notes'] ?? null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Warehouse release updated.',
            'data' => $pickup,
        ]);
    }

    public function arrive(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        CustomerAgentPermissions::assert($admin, CustomerAgentPermissions::HANDOVER, 'arrive');

        $data = $request->validate([
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $pickup = $this->workflow->recordAgentArrived($order, $admin, $data['notes'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Agent arrival recorded.',
            'data' => $pickup,
        ]);
    }

    public function handover(Order $order, Request $request): JsonResponse
    {
        $admin = $this->admin();
        CustomerAgentPermissions::assert($admin, CustomerAgentPermissions::HANDOVER, 'handover');

        $data = $request->validate([
            'signature' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'reference_number' => ['sometimes', 'nullable', 'string', 'max:191'],
            'document_number' => ['sometimes', 'nullable', 'string', 'max:191'],
            'photos' => ['sometimes', 'nullable', 'array', 'max:20'],
            'photos.*' => ['string', 'max:2048'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'agent_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'agent_contact' => ['sometimes', 'nullable', 'string', 'max:191'],
        ]);

        $pickup = $this->workflow->completeHandover($order, $admin, $data);

        return response()->json([
            'success' => true,
            'message' => 'Customer agent handover completed.',
            'data' => $pickup,
            'tracking' => $this->workflow->trackingPayload($order),
        ]);
    }

    private function admin(): Admin
    {
        /** @var Admin $admin */
        $admin = auth('sanctum')->user();

        return $admin;
    }
}
