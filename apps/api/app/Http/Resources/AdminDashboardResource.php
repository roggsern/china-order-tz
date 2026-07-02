<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardResource extends JsonResource
{
    /**
     * @param  array{
     *     total_products: int,
     *     total_categories: int,
     *     total_brands: int,
     *     total_suppliers: int,
     *     total_customers: int,
     *     total_orders: int,
     *     pending_orders: int,
     *     completed_orders: int,
     *     cancelled_orders: int,
     *     total_revenue: float,
     *     recent_orders: \Illuminate\Database\Eloquent\Collection
     * }  $resource
     */
    public function toArray(Request $request): array
    {
        return [
            'total_products' => $this->resource['total_products'],
            'total_categories' => $this->resource['total_categories'],
            'total_brands' => $this->resource['total_brands'],
            'total_suppliers' => $this->resource['total_suppliers'],
            'total_customers' => $this->resource['total_customers'],
            'total_orders' => $this->resource['total_orders'],
            'pending_orders' => $this->resource['pending_orders'],
            'completed_orders' => $this->resource['completed_orders'],
            'cancelled_orders' => $this->resource['cancelled_orders'],
            'total_revenue' => $this->resource['total_revenue'],
            'recent_orders' => OrderResource::collection($this->resource['recent_orders']),
        ];
    }
}
