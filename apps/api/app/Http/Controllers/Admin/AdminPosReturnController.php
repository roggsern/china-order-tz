<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PosReturnResource;
use App\Models\Admin;
use App\Models\Order;
use App\Models\ReturnReason;
use App\Models\ReturnRequest;
use App\Services\Pos\PosReturnService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPosReturnController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly PosReturnService $returns,
    ) {}

    public function reasons(): JsonResponse
    {
        $rows = ReturnReason::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'description']);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function search(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'receipt_number' => ['nullable', 'string', 'max:64'],
            'order_number' => ['nullable', 'string', 'max:64'],
            'store_id' => ['nullable', 'uuid', 'exists:stores,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $rows = $this->returns->searchOrders($admin, $filters);

        return response()->json([
            'success' => true,
            'data' => array_map(function (array $row) {
                return [
                    'eligible' => $row['eligible'],
                    'reason' => $row['reason'],
                    'returnable_items' => $row['returnable_items'],
                    'receipt' => [
                        'id' => $row['receipt']->id,
                        'receipt_number' => $row['receipt']->receipt_number,
                    ],
                    'order' => [
                        'id' => $row['order']->id,
                        'order_number' => $row['order']->order_number,
                        'total' => $row['order']->total,
                        'store_id' => $row['order']->store_id,
                        'customer_name' => $row['order']->user?->name ?? 'Walk-in Customer',
                        'paid_at' => $row['order']->paid_at,
                    ],
                ];
            }, $rows),
        ]);
    }

    public function orderPreview(Request $request, Order $order): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $data = $this->returns->lookupOrder($admin, $order);

        return response()->json([
            'success' => true,
            'data' => [
                'eligible' => $data['eligible'],
                'reason' => $data['reason'],
                'returnable_items' => $data['returnable_items'],
                'receipt' => $data['receipt'] ? [
                    'id' => $data['receipt']->id,
                    'receipt_number' => $data['receipt']->receipt_number,
                ] : null,
                'order' => [
                    'id' => $data['order']->id,
                    'order_number' => $data['order']->order_number,
                    'total' => $data['order']->total,
                    'store_id' => $data['order']->store_id,
                    'customer_name' => $data['order']->user?->name ?? 'Walk-in Customer',
                ],
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $data = $request->validate([
            'order_id' => ['required', 'uuid', 'exists:orders,id'],
            'return_type' => ['required', 'string', 'in:refund,exchange'],
            'return_reason_id' => ['nullable', 'uuid', 'exists:return_reasons,id'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'refund_method' => ['nullable', 'string', 'max:64'],
            'refund_reference' => ['nullable', 'string', 'max:120'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'uuid', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.inventory_disposition' => ['nullable', 'string', 'in:sellable,damaged,inspection'],
            'items.*.exchange_variant_id' => ['nullable', 'uuid', 'exists:product_variants,id'],
        ]);

        $result = $this->returns->process($admin, $data);

        return response()->json([
            'success' => true,
            'data' => [
                'return' => new PosReturnResource($result['return']),
                'refund' => $result['refund'],
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $filters = $request->validate([
            'store_id' => ['nullable', 'uuid', 'exists:stores,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paginator = $this->returns->list($admin, $filters);

        return response()->json([
            'success' => true,
            'data' => PosReturnResource::collection($paginator->getCollection()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Request $request, ReturnRequest $returnRequest): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $row = $this->returns->show($admin, $returnRequest);

        return response()->json([
            'success' => true,
            'data' => new PosReturnResource($row),
        ]);
    }

    public function orderReturns(Request $request, Order $order): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $this->returns->lookupOrder($admin, $order);

        $rows = ReturnRequest::query()
            ->with(['items.orderItem', 'latestRefund', 'processor', 'returnReason'])
            ->where('order_id', $order->id)
            ->where('sales_origin', 'pos')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => PosReturnResource::collection($rows),
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();
        $filters = $request->validate([
            'store_id' => ['nullable', 'uuid', 'exists:stores,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->returns->report($admin, $filters),
        ]);
    }
}
