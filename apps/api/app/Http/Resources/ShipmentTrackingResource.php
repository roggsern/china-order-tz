<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentTrackingResource extends JsonResource
{
    /**
     * @param  array<string, mixed>  $resource
     */
    public function toArray(Request $request): array
    {
        $shipment = $this->resource['shipment'] ?? null;
        $source = $this->resource['source'] ?? null;
        $timeline = $this->resource['timeline'] ?? [];

        if ($source === 'order_shipment_status') {
            $timeline = collect($timeline)
                ->map(fn (array $step) => [
                    'step' => $step['step'],
                    'completed' => $step['completed'],
                    'completed_at' => ($step['completed_at'] ?? null)?->toIso8601String(),
                    'description' => $step['description'],
                ])
                ->values()
                ->all();
        }

        return [
            'order_number' => $this->resource['order_number'],
            'current_status' => $this->resource['current_status'],
            'current_status_label' => $this->resource['current_status_label'] ?? null,
            'source' => $source,
            'tracking_ownership' => $this->resource['tracking_ownership'] ?? (
                $source === 'customer_agent_pickup' ? 'customer_agent' : 'company_shipment'
            ),
            'company_transport_tracking' => $this->resource['company_transport_tracking']
                ?? ($source !== 'customer_agent_pickup'),
            'shipment' => $shipment instanceof \App\Models\Shipment
                ? (new ShipmentResource($shipment))->resolve()
                : null,
            // Legacy alias kept for existing customers/admin clients.
            'shipment_summary' => $shipment instanceof \App\Models\Shipment
                ? (new ShipmentResource($shipment))->resolve()
                : null,
            'pickup' => $this->resource['pickup'] ?? null,
            'authorization_status' => $this->resource['authorization_status'] ?? null,
            'release_status' => $this->resource['release_status'] ?? null,
            'timeline' => $timeline,
            'unified_timeline' => $this->resource['unified_timeline'] ?? [],
        ];
    }
}
