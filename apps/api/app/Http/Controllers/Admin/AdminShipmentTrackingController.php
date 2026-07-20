<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShipmentTrackingEventRequest;
use App\Http\Resources\ShipmentResource;
use App\Http\Resources\ShipmentTrackingEventResource;
use App\Models\Admin;
use App\Models\Shipment;
use App\Services\Tracking\TrackingEngine;
use Illuminate\Http\JsonResponse;

class AdminShipmentTrackingController extends Controller
{
    public function index(Shipment $shipment, TrackingEngine $engine): JsonResponse
    {
        $payload = $engine->buildTrackingPayload($shipment);

        return response()->json([
            'success' => true,
            'data' => [
                'shipment' => new ShipmentResource($payload['shipment']),
                'current_status' => $payload['current_status'],
                'current_status_label' => $payload['current_status_label'],
                'timeline' => $payload['timeline'],
            ],
        ]);
    }

    public function store(
        Shipment $shipment,
        StoreShipmentTrackingEventRequest $request,
        TrackingEngine $engine,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = auth()->user();

        $event = $engine->recordEvent($shipment, $request->validated(), $admin);
        $payload = $engine->buildTrackingPayload($shipment->fresh() ?? $shipment);

        return response()->json([
            'success' => true,
            'message' => 'Tracking event recorded.',
            'data' => [
                'event' => new ShipmentTrackingEventResource($event->load('creator')),
                'shipment' => new ShipmentResource($payload['shipment']),
                'current_status' => $payload['current_status'],
                'current_status_label' => $payload['current_status_label'],
                'timeline' => $payload['timeline'],
            ],
        ], 201);
    }
}
