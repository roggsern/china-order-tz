<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SupportTicketResource;
use App\Models\Admin;
use App\Models\SupportTicket;
use App\Services\Support\SupportTicketEngine;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportTicketEngine $support,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize(AdminPermissions::SUPPORT_VIEW);

        /** @var Admin $admin */
        $admin = $request->user();

        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::in(SupportTicketStatus::values())],
            'category' => ['sometimes', 'nullable', Rule::in(SupportTicketCategory::values())],
            'priority' => ['sometimes', 'nullable', Rule::in(SupportTicketPriority::values())],
            'assigned_admin_id' => ['sometimes', 'nullable', 'uuid', 'exists:admins,id'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->support->paginateForAdmin($admin, $validated);

        return response()->json([
            'success' => true,
            'data' => SupportTicketResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorize(AdminPermissions::SUPPORT_VIEW);

        /** @var Admin $admin */
        $admin = $request->user();

        return response()->json([
            'success' => true,
            'data' => new SupportTicketResource($this->support->showForAdmin($admin, $ticket)),
        ]);
    }

    public function assign(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorize(AdminPermissions::SUPPORT_ASSIGN);

        /** @var Admin $admin */
        $admin = $request->user();

        $data = $request->validate([
            'admin_id' => ['required', 'uuid', 'exists:admins,id'],
        ]);

        $assignee = Admin::query()->findOrFail($data['admin_id']);
        $updated = $this->support->assign($admin, $ticket, $assignee);

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned.',
            'data' => new SupportTicketResource($updated),
        ]);
    }

    public function updateStatus(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorize(AdminPermissions::SUPPORT_MANAGE);

        /** @var Admin $admin */
        $admin = $request->user();

        $data = $request->validate([
            'status' => ['required', Rule::in(SupportTicketStatus::values())],
        ]);

        $updated = $this->support->updateStatus(
            $admin,
            $ticket,
            SupportTicketStatus::from($data['status']),
        );

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated.',
            'data' => new SupportTicketResource($updated),
        ]);
    }

    public function storeMessage(Request $request, SupportTicket $ticket): JsonResponse
    {
        $this->authorize(AdminPermissions::SUPPORT_MANAGE);

        /** @var Admin $admin */
        $admin = $request->user();

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'waiting_for_customer' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->support->addAdminMessage(
            $admin,
            $ticket,
            $data['message'],
            (bool) ($data['waiting_for_customer'] ?? false),
        );

        return response()->json([
            'success' => true,
            'message' => 'Reply sent.',
            'data' => new SupportTicketResource($updated),
        ]);
    }
}
