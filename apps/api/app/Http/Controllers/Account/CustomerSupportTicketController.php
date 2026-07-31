<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Support\SupportTicketEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Enums\SupportTicketCategory;
use App\Enums\SupportTicketPriority;

class CustomerSupportTicketController extends Controller
{
    public function __construct(
        private readonly SupportTicketEngine $support,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', 'string'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $paginator = $this->support->paginateForCustomer($user, $validated);

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

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::in(SupportTicketCategory::values())],
            'priority' => ['sometimes', Rule::in(SupportTicketPriority::values())],
            'order_id' => ['nullable', 'uuid', 'exists:orders,id'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = $this->support->createForCustomer($user, $data);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created.',
            'data' => new SupportTicketResource($ticket),
        ], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => new SupportTicketResource($this->support->showForCustomer($user, $ticket)),
        ]);
    }

    public function storeMessage(Request $request, SupportTicket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $updated = $this->support->addCustomerMessage($user, $ticket, $data['message']);

        return response()->json([
            'success' => true,
            'message' => 'Reply sent.',
            'data' => new SupportTicketResource($updated),
        ]);
    }
}
