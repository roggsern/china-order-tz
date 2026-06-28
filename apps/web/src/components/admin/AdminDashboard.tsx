"use client";

import Link from "next/link";
import { useMemo } from "react";
import { useAdminProducts } from "@/components/admin/AdminProductsProvider";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";
import { AdminStatCard } from "@/components/admin/AdminStatCard";
import { AdminDeliveriesSection } from "@/components/admin/AdminDeliveriesSection";
import { computeOrderAnalytics } from "@/lib/admin/order-analytics";
import { formatPrice } from "@/lib/catalog/utils";
import { getCategoryBySlug } from "@/lib/catalog/categories";
import { getOrderFulfillmentLabel } from "@/lib/payment/order-filters";
import { PlusIcon, PackageIcon, DocumentIcon } from "@/components/home/icons";
import { StatusBadge } from "@/components/admin/StatusBadge";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { OrderLiveStatusIndicator } from "@/components/admin/OrderLiveStatusIndicator";

function formatOrderDate(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

export function AdminDashboard() {
  const { products } = useAdminProducts();
  const { orders, isHydrated: ordersHydrated } = useAdminOrders();
  const orderAnalytics = useMemo(() => computeOrderAnalytics(orders), [orders]);

  const recentOrders = useMemo(
    () => [...orders].sort((a, b) => b.createdAt.localeCompare(a.createdAt)).slice(0, 6),
    [orders],
  );

  const recentProducts = [...products].sort((a, b) => b.id - a.id).slice(0, 4);
  const activeCount = products.filter((p) => p.status === "active").length;

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <div className="admin-page-header">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            Store overview
          </p>
          <h1 className="mt-1 text-2xl font-bold text-zinc-900 sm:text-3xl">Dashboard</h1>
          <p className="mt-1 text-sm text-zinc-500">
            Live order metrics — updates via WebSocket or polling depending on environment.
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link href="/admin/orders" className="admin-btn-secondary">
            Manage orders
          </Link>
          <Link href="/admin/products/new" className="admin-btn-primary inline-flex items-center gap-2">
            <PlusIcon className="h-4 w-4" />
            Add product
          </Link>
        </div>
      </div>

      <section className="mt-8">
        <h2 className="text-sm font-bold text-zinc-900">Order analytics</h2>
        <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <AdminStatCard
            label="Total orders"
            value={ordersHydrated ? orderAnalytics.totalOrders : "—"}
            href="/admin/orders"
            variant="dark"
            livePulse
          />
          <AdminStatCard
            label="Total revenue"
            value={ordersHydrated ? formatPrice(orderAnalytics.totalRevenue) : "—"}
            sub="From paid orders"
            isText
            accent="text-[#c9a227]"
            variant="gold"
            livePulse
          />
          <AdminStatCard
            label="Pending orders"
            value={ordersHydrated ? orderAnalytics.pendingOrders : "—"}
            sub="Awaiting completion"
            accent="text-red-600"
            livePulse
          />
          <AdminStatCard
            label="Paid orders"
            value={ordersHydrated ? orderAnalytics.paidOrders : "—"}
            sub="Payment confirmed"
            accent="text-emerald-600"
            livePulse
          />
          <AdminStatCard
            label="Active deliveries"
            value={ordersHydrated ? orderAnalytics.activeDeliveries : "—"}
            sub="Packed, shipped, or in transit"
            accent="text-indigo-600"
            livePulse
          />
        </div>
      </section>

      <section className="mt-8">
        <AdminDeliveriesSection />
      </section>

      <div className="mt-8 grid gap-6 xl:grid-cols-3">
        <section className="admin-card overflow-hidden xl:col-span-2">
          <div className="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
            <div className="flex items-center gap-2">
              <DocumentIcon className="h-4 w-4 text-[#8b6914]" />
              <h2 className="text-sm font-bold text-zinc-900">Recent orders</h2>
            </div>
            <Link href="/admin/orders" className="text-xs font-semibold text-[#8b6914] hover:underline">
              View all
            </Link>
          </div>

          {!ordersHydrated ? (
            <div className="p-8">
              <div className="h-40 animate-pulse rounded-xl bg-zinc-50" />
            </div>
          ) : recentOrders.length === 0 ? (
            <div className="px-6 py-12 text-center text-sm text-zinc-500">No orders yet.</div>
          ) : (
            <>
              <div className="hidden overflow-x-auto md:block">
                <table className="w-full min-w-[640px] text-left text-sm">
                  <thead>
                    <tr className="border-b border-zinc-100 bg-zinc-50/80">
                      <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        Order ID
                      </th>
                      <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        Customer
                      </th>
                      <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        Total
                      </th>
                      <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                        Status
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-zinc-100">
                    {recentOrders.map((order) => {
                      const customerName =
                        `${order.customer.firstName} ${order.customer.lastName}`.trim();
                      return (
                        <tr key={order.id} className="hover:bg-zinc-50/80">
                          <td className="px-4 py-3">
                            <Link
                              href={`/admin/orders/${order.id}`}
                              className="font-mono text-sm font-semibold text-zinc-900 hover:text-[#8b6914]"
                            >
                              {order.id.slice(0, 8)}
                            </Link>
                          </td>
                          <td className="px-4 py-3">
                            <p className="font-medium text-zinc-900">{customerName || "—"}</p>
                          </td>
                          <td className="px-4 py-3 font-semibold">
                            {formatPrice(order.grandTotal ?? order.totals.grandTotal)}
                          </td>
                          <td className="px-4 py-3">
                            <div className="flex flex-wrap items-center gap-1.5">
                              <OrderLiveStatusIndicator order={order} showLabel />
                              <PaymentStatusBadge status={order.paymentStatus} size="sm" />
                              <span className="inline-flex rounded-md bg-zinc-100 px-2 py-0.5 text-[11px] font-semibold text-zinc-700">
                                {getOrderFulfillmentLabel(order.status)}
                              </span>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>

              <ul className="divide-y divide-zinc-100 md:hidden">
                {recentOrders.map((order) => {
                  const customerName =
                    `${order.customer.firstName} ${order.customer.lastName}`.trim();
                  return (
                    <li key={order.id}>
                      <Link
                        href={`/admin/orders/${order.id}`}
                        className="block px-4 py-4 transition hover:bg-zinc-50"
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div>
                            <p className="font-mono text-sm font-semibold text-zinc-900">
                              {order.id.slice(0, 8)}
                            </p>
                            <p className="mt-0.5 text-sm text-zinc-600">{customerName || "—"}</p>
                            <p className="mt-1 text-xs text-zinc-400">
                              {formatOrderDate(order.createdAt)}
                            </p>
                          </div>
                          <div className="text-right">
                            <p className="font-semibold text-zinc-900">
                              {formatPrice(order.grandTotal ?? order.totals.grandTotal)}
                            </p>
                            <div className="mt-2 flex flex-wrap items-center justify-end gap-1">
                              <OrderLiveStatusIndicator order={order} />
                              <PaymentStatusBadge status={order.paymentStatus} size="sm" />
                            </div>
                          </div>
                        </div>
                      </Link>
                    </li>
                  );
                })}
              </ul>
            </>
          )}
        </section>

        <section className="admin-card overflow-hidden">
          <div className="flex items-center justify-between border-b border-zinc-200 px-5 py-4">
            <div className="flex items-center gap-2">
              <PackageIcon className="h-4 w-4 text-[#8b6914]" />
              <h2 className="text-sm font-bold text-zinc-900">Catalog</h2>
            </div>
            <Link href="/admin/products" className="text-xs font-semibold text-[#8b6914] hover:underline">
              View all
            </Link>
          </div>
          <div className="grid grid-cols-2 gap-3 p-4">
            <div className="rounded-xl bg-zinc-50 p-3">
              <p className="text-xs text-zinc-500">Products</p>
              <p className="mt-1 text-xl font-bold text-zinc-900">{products.length}</p>
            </div>
            <div className="rounded-xl bg-zinc-50 p-3">
              <p className="text-xs text-zinc-500">Active</p>
              <p className="mt-1 text-xl font-bold text-emerald-600">{activeCount}</p>
            </div>
          </div>
          <ul className="divide-y divide-zinc-100 border-t border-zinc-100">
            {recentProducts.map((product) => {
              const category = getCategoryBySlug(product.categorySlug);
              return (
                <li key={product.id}>
                  <Link
                    href={`/admin/products/${product.id}/edit`}
                    className="flex items-center gap-3 px-4 py-3 transition hover:bg-zinc-50"
                  >
                    <div
                      className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br ${product.gradient}`}
                    >
                      <span className="text-base">{product.emoji}</span>
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium text-zinc-900">{product.name}</p>
                      <p className="text-xs text-zinc-500">{category?.name}</p>
                    </div>
                    <StatusBadge status={product.status} />
                  </Link>
                </li>
              );
            })}
          </ul>
        </section>
      </div>
    </div>
  );
}
