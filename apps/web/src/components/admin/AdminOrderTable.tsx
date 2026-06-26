"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import {
  ADMIN_ORDER_LIST_FILTERS,
  type AdminOrderListFilter,
  countOrdersByListFilter,
  filterOrdersByListFilter,
  getOrderFulfillmentLabel,
  getOrderShippingMethodLabel,
} from "@/lib/payment/order-filters";
import { formatPrice } from "@/lib/catalog/utils";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";

function formatOrderDate(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

function shortenOrderId(orderId: string): string {
  return orderId.slice(0, 8);
}

function getDisplayPaymentStatus(order: Order): "pending" | "paid" | "failed" {
  if (order.paymentStatus === PAYMENT_STATUS.PAID) return "paid";
  if (order.paymentStatus === PAYMENT_STATUS.FAILED) return "failed";
  return "pending";
}

export function AdminOrderTable() {
  const { orders, isHydrated, markPaymentReceived, markOrderShipped, markOrderDelivered } =
    useAdminOrders();
  const [activeFilter, setActiveFilter] = useState<AdminOrderListFilter>("all");
  const [search, setSearch] = useState("");

  const filterCounts = useMemo(() => countOrdersByListFilter(orders), [orders]);

  const filtered = useMemo(() => {
    const listOrders = filterOrdersByListFilter(orders, activeFilter);
    const query = search.toLowerCase().trim();

    if (!query) {
      return listOrders;
    }

    return listOrders.filter((order) => {
      const customerName = `${order.customer.firstName} ${order.customer.lastName}`.toLowerCase();
      return (
        order.id.toLowerCase().includes(query) ||
        order.orderNumber.toLowerCase().includes(query) ||
        order.customer.email.toLowerCase().includes(query) ||
        customerName.includes(query)
      );
    });
  }, [orders, activeFilter, search]);

  if (!isHydrated) {
    return (
      <div className="admin-card p-8">
        <div className="h-8 w-48 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-6 h-64 animate-pulse rounded-lg bg-zinc-50" />
      </div>
    );
  }

  const activeFilterMeta = ADMIN_ORDER_LIST_FILTERS.find((entry) => entry.id === activeFilter);

  return (
    <div className="admin-card overflow-hidden">
      <div className="border-b border-zinc-200 p-4">
        <div
          className="flex gap-2 overflow-x-auto pb-1"
          role="tablist"
          aria-label="Order filters"
        >
          {ADMIN_ORDER_LIST_FILTERS.map((filter) => {
            const isActive = activeFilter === filter.id;
            const count = filterCounts[filter.id];

            return (
              <button
                key={filter.id}
                type="button"
                role="tab"
                aria-selected={isActive}
                aria-controls={`orders-panel-${filter.id}`}
                id={`orders-tab-${filter.id}`}
                onClick={() => setActiveFilter(filter.id)}
                className={`inline-flex shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition ${
                  isActive
                    ? "bg-zinc-900 text-white shadow-sm"
                    : "bg-zinc-50 text-zinc-600 hover:bg-zinc-100 hover:text-zinc-900"
                }`}
              >
                {filter.label}
                <span
                  className={`rounded-full px-1.5 py-0.5 text-[11px] font-semibold ${
                    isActive ? "bg-white/15 text-white" : "bg-zinc-200 text-zinc-700"
                  }`}
                >
                  {count}
                </span>
              </button>
            );
          })}
        </div>

        <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center">
          <input
            type="search"
            value={search}
            onChange={(event) => setSearch(event.target.value)}
            placeholder="Search by order ID, number, or customer"
            className="admin-input max-w-md"
            aria-label="Search orders"
          />
          <p className="text-xs text-zinc-500 sm:ml-auto">
            {filtered.length} of {filterCounts[activeFilter]} in {activeFilterMeta?.label.toLowerCase()}
          </p>
        </div>
      </div>

      <div
        id={`orders-panel-${activeFilter}`}
        role="tabpanel"
        aria-labelledby={`orders-tab-${activeFilter}`}
      >
        {filtered.length === 0 ? (
          <div className="flex flex-col items-center px-6 py-16 text-center">
            <span className="text-4xl" aria-hidden>
              📋
            </span>
            <p className="mt-4 text-sm font-medium text-zinc-700">
              No {activeFilterMeta?.label.toLowerCase()} orders
            </p>
            <p className="mt-1 text-xs text-zinc-500">{activeFilterMeta?.description}</p>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[1100px] text-left text-sm">
              <thead>
                <tr className="border-b border-zinc-200 bg-zinc-50/80">
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
                    Payment
                  </th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    Order Status
                  </th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    Shipping
                  </th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    Placed
                  </th>
                  <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    Actions
                  </th>
                </tr>
              </thead>
              <tbody className="divide-y divide-zinc-100">
                {filtered.map((order) => (
                  <OrderRow
                    key={order.id}
                    order={order}
                    onMarkPaid={() => markPaymentReceived(order.id)}
                    onMarkShipped={() => markOrderShipped(order.id)}
                    onMarkDelivered={() => markOrderDelivered(order.id)}
                  />
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}

function OrderRow({
  order,
  onMarkPaid,
  onMarkShipped,
  onMarkDelivered,
}: {
  order: Order;
  onMarkPaid: () => void;
  onMarkShipped: () => void;
  onMarkDelivered: () => void;
}) {
  const customerName = `${order.customer.firstName} ${order.customer.lastName}`.trim();
  const paymentStatus = getDisplayPaymentStatus(order);
  const canMarkPaid = paymentStatus !== "paid";
  const canMarkShipped =
    order.status !== ORDER_STATUS.SHIPPED && order.status !== ORDER_STATUS.DELIVERED;
  const canMarkDelivered = order.status !== ORDER_STATUS.DELIVERED;

  return (
    <tr className="transition hover:bg-zinc-50/80">
      <td className="px-4 py-3">
        <Link
          href={`/admin/orders/${order.id}`}
          className="font-mono text-sm font-semibold text-zinc-900 hover:text-[#8b6914] hover:underline"
          title={order.id}
        >
          {shortenOrderId(order.id)}
        </Link>
        <p className="mt-0.5 font-mono text-xs text-zinc-400">{order.orderNumber}</p>
      </td>
      <td className="px-4 py-3">
        <p className="font-medium text-zinc-900">{customerName || "—"}</p>
        <p className="text-xs text-zinc-500">{order.customer.email}</p>
      </td>
      <td className="px-4 py-3 font-semibold text-zinc-900">
        {formatPrice(order.grandTotal ?? order.totals.grandTotal)}
      </td>
      <td className="px-4 py-3">
        <PaymentStatusBadge status={order.paymentStatus} size="sm" />
      </td>
      <td className="px-4 py-3">
        <span className="inline-flex rounded-md bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-700">
          {getOrderFulfillmentLabel(order.status)}
        </span>
      </td>
      <td className="px-4 py-3 text-sm text-zinc-700">
        {getOrderShippingMethodLabel(order)}
      </td>
      <td className="px-4 py-3 text-xs text-zinc-500">{formatOrderDate(order.createdAt)}</td>
      <td className="px-4 py-3">
        <div className="flex flex-wrap items-center justify-end gap-2">
          <Link
            href={`/admin/orders/${order.id}`}
            className="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-600 transition hover:bg-zinc-50"
          >
            View
          </Link>
          {canMarkPaid && (
            <button
              type="button"
              onClick={onMarkPaid}
              className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
            >
              Mark paid
            </button>
          )}
          {canMarkShipped && (
            <button
              type="button"
              onClick={onMarkShipped}
              className="rounded-lg bg-[#c9a227] px-3 py-1.5 text-xs font-semibold text-zinc-900 transition hover:bg-[#e8c547]"
            >
              Mark shipped
            </button>
          )}
          {canMarkDelivered && (
            <button
              type="button"
              onClick={onMarkDelivered}
              className="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50"
            >
              Mark delivered
            </button>
          )}
        </div>
      </td>
    </tr>
  );
}
