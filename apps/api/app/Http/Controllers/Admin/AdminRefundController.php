<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveAdminRefundRequest;
use App\Http\Requests\Admin\IndexAdminRefundsRequest;
use App\Http\Requests\Admin\ProcessAdminRefundRequest;
use App\Http\Requests\Admin\RejectAdminRefundRequest;
use App\Http\Requests\Admin\ShowAdminRefundRequest;
use App\Http\Requests\Admin\StoreAdminRefundRequest;
use App\Http\Resources\RefundTransactionResource;
use App\Models\Admin;
use App\Models\RefundTransaction;
use App\Services\Refunds\RefundOperationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminRefundController extends Controller
{
    public function __construct(
        private readonly RefundOperationsService $refundOperations,
    ) {}

    public function index(IndexAdminRefundsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated('per_page') ?? 20);

        return RefundTransactionResource::collection(
            $this->refundOperations->paginate($request->validated(), $perPage),
        )->additional(['success' => true]);
    }

    public function show(RefundTransaction $refund, ShowAdminRefundRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new RefundTransactionResource($this->refundOperations->show($refund)),
        ]);
    }

    public function store(StoreAdminRefundRequest $request): JsonResponse
    {
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        $refund = $this->refundOperations->create($request->validated(), $admin);

        return response()->json([
            'success' => true,
            'message' => 'Refund request created.',
            'data' => new RefundTransactionResource($refund),
        ], 201);
    }

    public function approve(RefundTransaction $refund, ApproveAdminRefundRequest $request): JsonResponse
    {
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        $updated = $this->refundOperations->approve(
            $refund,
            $admin,
            $request->validated('notes'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund approved.',
            'data' => new RefundTransactionResource($updated),
        ]);
    }

    public function reject(RefundTransaction $refund, RejectAdminRefundRequest $request): JsonResponse
    {
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        $updated = $this->refundOperations->reject(
            $refund,
            $admin,
            $request->validated('reason'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund rejected.',
            'data' => new RefundTransactionResource($updated),
        ]);
    }

    public function process(RefundTransaction $refund, ProcessAdminRefundRequest $request): JsonResponse
    {
        $admin = auth('sanctum')->user();
        abort_unless($admin instanceof Admin, 403);

        $updated = $this->refundOperations->process(
            $refund,
            $admin,
            $request->validated('notes'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Refund processed.',
            'data' => new RefundTransactionResource($updated),
        ]);
    }
}
