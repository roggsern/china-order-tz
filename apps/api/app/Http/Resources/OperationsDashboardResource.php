<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationsDashboardResource extends JsonResource
{
    /**
     * @param  array{
     *     summary: array{
     *         total_orders: int,
     *         pending_payments: int,
     *         total_customers: int,
     *         total_products: int
     *     },
     *     shipments: array<string, int>,
     *     alerts: list<array{type: string, message: string, count: int}>
     * }  $resource
     */
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this->resource['summary'],
            'shipments' => $this->resource['shipments'],
            'alerts' => $this->resource['alerts'],
        ];
    }
}
