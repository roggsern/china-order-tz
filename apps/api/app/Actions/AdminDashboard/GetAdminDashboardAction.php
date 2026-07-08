<?php

namespace App\Actions\AdminDashboard;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class GetAdminDashboardAction
{
    /**
     * @return array{
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
     *     recent_orders: Collection<int, Order>
     * }
     */
    public function handle(): array
    {
        return [
            'total_products' => Product::count(),
            'total_categories' => Category::count(),
            'total_brands' => Brand::count(),
            'total_suppliers' => Supplier::count(),
            'total_customers' => User::count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', OrderStatus::Pending)->count(),
            'completed_orders' => Order::where('status', OrderStatus::Delivered)->count(),
            'cancelled_orders' => Order::where('status', OrderStatus::Cancelled)->count(),
            'total_revenue' => (float) Payment::where('status', PaymentStatus::Paid)->sum('amount'),
            'recent_orders' => Order::query()
                ->with(['user'])
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
}
