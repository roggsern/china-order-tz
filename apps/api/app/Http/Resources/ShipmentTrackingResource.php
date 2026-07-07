<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipmentTrackingResource extends JsonResource
{
    /**
     * @param  array{
     *     order_number: string,
     *     current_status: string,
     *     timeline: list<array{
     *         step: string,
     *         completed: bool,
     *         completed_at: \Illuminate\Support\Carbon|null,
     *         description: string
     *     }>
     * }  $resource
     */
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->resource['order_number'],
            'current_status' => $this->resource['current_status'],
            'timeline' => collect($this->resource['timeline'])
                ->map(fn (array $step) => [
                    'step' => $step['step'],
                    'completed' => $step['completed'],
                    'completed_at' => $step['completed_at']?->toIso8601String(),
                    'description' => $step['description'],
                ])
                ->values()
                ->all(),
        ];
    }
}
