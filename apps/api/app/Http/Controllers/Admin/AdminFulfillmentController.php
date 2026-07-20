<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexFulfillmentsRequest;
use App\Http\Requests\Admin\UpdateFulfillmentStatusRequest;
use App\Http\Resources\FulfillmentResource;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Services\Fulfillment\FulfillmentEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminFulfillmentController extends Controller
{
    public function index(IndexFulfillmentsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated('per_page') ?? 20);

        $query = Fulfillment::query()
            ->with(['order.user', 'assignee'])
            ->latest();

        if ($strategy = $request->validated('strategy')) {
            $query->where('strategy', $strategy);
        }

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($orderId = $request->validated('order_id')) {
            $query->where('order_id', $orderId);
        }

        return FulfillmentResource::collection($query->paginate($perPage))
            ->additional(['success' => true]);
    }

    public function create(Order $order, FulfillmentEngine $engine): JsonResponse
    {
        $fulfillment = $engine->createForOrder($order);

        return response()->json([
            'success' => true,
            'message' => 'Fulfillment created.',
            'data' => new FulfillmentResource($fulfillment),
        ], 201);
    }

    public function show(Fulfillment $fulfillment, FulfillmentEngine $engine): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new FulfillmentResource($engine->show($fulfillment)),
        ]);
    }

    public function updateStatus(
        Fulfillment $fulfillment,
        UpdateFulfillmentStatusRequest $request,
        FulfillmentEngine $engine,
    ): JsonResponse {
        $updated = $engine->updateStatus($fulfillment, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Fulfillment status updated.',
            'data' => new FulfillmentResource($updated),
        ]);
    }
}
