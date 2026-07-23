<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexAdminReturnsRequest;
use App\Http\Requests\Admin\StoreReturnRefundRequest;
use App\Http\Requests\Admin\UpdateReturnRequestStatusRequest;
use App\Http\Resources\RefundTransactionResource;
use App\Http\Resources\ReturnRequestResource;
use App\Models\Admin;
use App\Models\ReturnRequest;
use App\Services\Returns\RefundEngine;
use App\Services\Returns\ReturnEngine;
use App\Support\Admin\AdminPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminReturnController extends Controller
{
    public function __construct(
        private readonly ReturnEngine $returnEngine,
        private readonly RefundEngine $refundEngine,
    ) {}

    public function index(IndexAdminReturnsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated('per_page') ?? 20);

        return ReturnRequestResource::collection(
            $this->returnEngine->paginateForAdmin($request->validated(), $perPage)
        )->additional(['success' => true]);
    }

    public function show(ReturnRequest $returnRequest): JsonResponse
    {
        $this->authorize(AdminPermissions::RETURNS_VIEW);

        return response()->json([
            'success' => true,
            'data' => new ReturnRequestResource($this->returnEngine->show($returnRequest)),
        ]);
    }

    public function updateStatus(
        ReturnRequest $returnRequest,
        UpdateReturnRequestStatusRequest $request,
    ): JsonResponse {
        $admin = auth('sanctum')->user();
        $updated = $this->returnEngine->updateStatus(
            $returnRequest,
            $request->validated(),
            $admin instanceof Admin ? $admin : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Return status updated.',
            'data' => new ReturnRequestResource($updated),
        ]);
    }

    public function createRefund(
        ReturnRequest $returnRequest,
        StoreReturnRefundRequest $request,
    ): JsonResponse {
        $admin = auth('sanctum')->user();
        $adminModel = $admin instanceof Admin ? $admin : null;

        $validated = $request->validated();
        $targetStatus = $validated['status'] ?? null;
        unset($validated['status']);

        $refund = $this->refundEngine->createForReturn($returnRequest, $validated, $adminModel);

        if ($targetStatus && $targetStatus !== 'pending') {
            // Controlled advance — never skips validation of allowed transitions.
            $path = match ($targetStatus) {
                'approved' => ['approved'],
                'processing' => ['approved', 'processing'],
                'completed' => ['approved', 'processing', 'completed'],
                'failed' => ['failed'],
                default => [],
            };
            foreach ($path as $step) {
                $refund = $this->refundEngine->updateStatus(
                    $refund,
                    ['status' => $step],
                    $adminModel,
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Refund transaction created.',
            'data' => new RefundTransactionResource(
                $refund->loadMissing(['returnRequest', 'order'])
            ),
        ], 201);
    }
}
