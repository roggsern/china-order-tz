<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * @param  array{
     *     customer: array{id: string, name: string},
     *     summary: array{
     *         active_orders: int,
     *         in_transit_orders: int,
     *         pending_payments: int,
     *         completed_orders: int
     *     },
     *     recent_orders: \Illuminate\Support\Collection<int, array{
     *         id: string,
     *         order_number: string,
     *         source: string,
     *         status: string,
     *         created_at: \Illuminate\Support\Carbon|null
     *     }>,
     *     quick_actions: list<array{label: string}>
     * }  $resource
     */
    public function toArray(Request $request): array
    {
        return [
            'customer' => $this->resource['customer'],
            'summary' => $this->resource['summary'],
            'recent_orders' => $this->resource['recent_orders']->values()->all(),
            'quick_actions' => $this->resource['quick_actions'],
        ];
    }
}
