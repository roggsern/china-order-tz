<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\IndexShipmentsRequest;
use App\Http\Requests\Admin\StoreShipmentRequest;
use App\Http\Requests\Admin\UpdateShipmentLifecycleRequest;
use App\Http\Resources\ShipmentResource;
use App\Models\Fulfillment;
use App\Models\Shipment;
use App\Services\Shipments\ShipmentEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Operational Shipment Engine admin API (physical transport records).
 * Distinct from AdminShipmentController (order timeline shipment_status).
 */
class AdminShipmentsController extends Controller
{
    public function index(IndexShipmentsRequest $request): AnonymousResourceCollection
    {
        $perPage = (int) ($request->validated('per_page') ?? 20);

        $query = Shipment::query()
            ->whereNotNull('fulfillment_id')
            ->with(['order.user', 'fulfillment.order.user', 'fulfillment.order.deliveryOption'])
            ->latest();

        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }

        if ($mode = $request->validated('transport_mode')) {
            $query->where('transport_mode', $mode);
        }

        if ($orderId = $request->validated('order_id')) {
            $query->where('order_id', $orderId);
        }

        return ShipmentResource::collection($query->paginate($perPage))
            ->additional(['success' => true]);
    }

    public function create(
        Fulfillment $fulfillment,
        StoreShipmentRequest $request,
        ShipmentEngine $engine,
    ): JsonResponse {
        $shipment = $engine->createForFulfillment($fulfillment, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Shipment created.',
            'data' => new ShipmentResource($shipment),
        ], 201);
    }

    public function show(Shipment $shipment, ShipmentEngine $engine): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new ShipmentResource($engine->show($shipment)),
        ]);
    }

    public function eligibility(Fulfillment $fulfillment, ShipmentEngine $engine): JsonResponse
    {
        $result = $engine->eligibilityFor($fulfillment);

        return response()->json([
            'success' => true,
            'data' => [
                'eligible' => $result['eligible'],
                'reason' => $result['reason'],
                'transport_mode' => $result['transport_mode'],
                'delivery_type' => $result['delivery_type'],
                'shipment' => $result['shipment']
                    ? new ShipmentResource($result['shipment'])
                    : null,
            ],
        ]);
    }

    public function updateStatus(
        Shipment $shipment,
        UpdateShipmentLifecycleRequest $request,
        ShipmentEngine $engine,
    ): JsonResponse {
        $updated = $engine->updateMetadata($shipment, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Shipment metadata updated. Status is managed by tracking events.',
            'data' => new ShipmentResource($updated),
        ]);
    }
}
