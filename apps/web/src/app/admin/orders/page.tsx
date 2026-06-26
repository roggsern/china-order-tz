"use client";

import { useMemo } from "react";
import Link from "next/link";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";
import { AdminOrderTable } from "@/components/admin/AdminOrderTable";
import { computeOrderAnalytics } from "@/lib/admin/order-analytics";
import { countOrdersByListFilter } from "@/lib/payment/order-filters";
import { formatPrice } from "@/lib/catalog/utils";

export default function AdminOrdersPage() {
  const { orders, isHydrated } = useAdminOrders();
  const analytics = useMemo(() => computeOrderAnalytics(orders), [orders]);
  const filterCounts = useMemo(() => countOrdersByListFilter(orders), [orders]);

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <header>
        <h1 className="text-xl font-semibold text-zinc-900 sm:text-2xl">Orders</h1>
        <p className="mt-0.5 text-sm text-zinc-500">
          Manage payments, fulfillment, and order lifecycle from frozen checkout snapshots.
        </p>
      </header>

      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Total orders"
          value={isHydrated ? analytics.totalOrders : "—"}
          href="/admin/orders"
        />
        <StatCard
          label="Total revenue"
          value={isHydrated ? formatPrice(analytics.totalRevenue) : "—"}
          accent="text-emerald-600"
          isText
        />
        <StatCard
          label="Paid orders"
          value={isHydrated ? analytics.paidOrders : "—"}
          accent="text-[#8b6914]"
        />
        <StatCard
          label="Pending payments"
          value={isHydrated ? analytics.pendingPayments : "—"}
          accent="text-amber-600"
        />
      </div>

      <div className="mt-4 flex flex-wrap gap-2 text-xs text-zinc-500">
        <span>{filterCounts.shipped} shipped</span>
        <span aria-hidden>·</span>
        <span>{filterCounts.delivered} delivered</span>
      </div>

      <div className="mt-6">
        <AdminOrderTable />
      </div>
    </div>
  );
}

function StatCard({
  label,
  value,
  accent = "text-zinc-900",
  href,
  isText,
}: {
  label: string;
  value: number | string;
  accent?: string;
  href?: string;
  isText?: boolean;
}) {
  const content = (
    <div className="admin-card p-4 transition hover:shadow-md">
      <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">{label}</p>
      <p className={`mt-1 font-semibold ${accent} ${isText ? "text-lg" : "text-2xl"}`}>{value}</p>
    </div>
  );

  if (href) {
    return <Link href={href}>{content}</Link>;
  }

  return content;
}
