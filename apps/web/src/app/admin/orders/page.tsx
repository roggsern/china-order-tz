"use client";

import { useMemo } from "react";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";
import { AdminOrderTable } from "@/components/admin/AdminOrderTable";
import { AdminStatCard } from "@/components/admin/AdminStatCard";
import { AdminOrderStatusLegend } from "@/components/admin/AdminOrderStatusLegend";
import { computeOrderAnalytics } from "@/lib/admin/order-analytics";
import { formatPrice } from "@/lib/catalog/utils";

export default function AdminOrdersPage() {
  const { orders, isHydrated } = useAdminOrders();
  const analytics = useMemo(() => computeOrderAnalytics(orders), [orders]);

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <header className="admin-page-header">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            Order management
          </p>
          <h1 className="mt-1 text-2xl font-bold text-zinc-900 sm:text-3xl">Orders</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Live order control — updates via WebSocket or polling as customers checkout and pay.
          </p>
        </div>
      </header>

      <div className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <AdminStatCard
          label="Total orders"
          value={isHydrated ? analytics.totalOrders : "—"}
          variant="dark"
          livePulse
        />
        <AdminStatCard
          label="Total revenue"
          value={isHydrated ? formatPrice(analytics.totalRevenue) : "—"}
          accent="text-[#c9a227]"
          isText
          variant="gold"
          livePulse
        />
        <AdminStatCard
          label="Pending orders"
          value={isHydrated ? analytics.pendingOrders : "—"}
          accent="text-red-600"
          livePulse
        />
        <AdminStatCard
          label="Paid orders"
          value={isHydrated ? analytics.paidOrders : "—"}
          accent="text-emerald-600"
          livePulse
        />
      </div>

      <AdminOrderStatusLegend />

      <div className="mt-6">
        <AdminOrderTable />
      </div>
    </div>
  );
}
