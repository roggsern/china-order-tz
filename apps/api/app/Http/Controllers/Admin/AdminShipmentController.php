<?php

namespace App\Http\Controllers\Admin;

use App\Actions\AdminShipments\UpdateShipmentStatusAction;
use App\Enums\ShipmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateShipmentStatusRequest;
use App\Models\Admin;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class AdminShipmentController extends Controller
{
    public function update(
        UpdateShipmentStatusRequest $request,
        Order $order,
        UpdateShipmentStatusAction $action,
    ): JsonResponse {
        /** @var Admin $admin */
        $admin = auth()->user();

        $order = $action->handle(
            $order,
            $admin,
            $request->enum('shipment_status', ShipmentStatus::class),
        );

        return response()->json([
            'success' => true,
            'message' => 'Shipment status updated successfully.',
            'data' => [
                'order_id' => $order->id,
                'shipment_status' => $order->shipment_status->value,
                'updated_at' => $order->shipment_status_updated_at?->toIso8601String(),
            ],
        ]);
    }
}
