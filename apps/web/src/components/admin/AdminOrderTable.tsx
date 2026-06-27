"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import type { Order, OrderStatus } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import {
  ADMIN_ORDER_LIST_FILTERS,
  type AdminOrderListFilter,
  countOrdersByListFilter,
  filterOrdersByListFilter,
  getOrderShippingMethodLabel,
} from "@/lib/payment/order-filters";
import { formatPrice } from "@/lib/catalog/utils";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { OrderStatusSelect } from "@/components/admin/OrderStatusSelect";

function formatOrderDate(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

function shortenOrderId(orderId: string): string {
  return orderId.slice(0, 8).toUpperCase();
}

function normalizeStatusForSelect(status: OrderStatus): OrderStatus {
  if (
    status === ORDER_STATUS.CONFIRMED ||
    status === ORDER_STATUS.PENDING_PAYMENT ||
    status === ORDER_STATUS.PENDING
  ) {
    return ORDER_STATUS.PENDING;
  }
  if (
    status === ORDER_STATUS.PROCESSING ||
    status === ORDER_STATUS.PACKED ||
    status === ORDER_STATUS.SHIPPED ||
    status === ORDER_STATUS.IN_TRANSIT ||
    status === ORDER_STATUS.DELIVERED
  ) {
    return status;
  }
  return ORDER_STATUS.PENDING;
}

export function AdminOrderTable() {
  const {
    orders,
    isHydrated,
    markPaymentReceived,
    markOrderDelivered,
    updateOrderStatus,
  } = useAdminOrders();
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
            {filtered.length} of {filterCounts[activeFilter]} in{" "}
            {activeFilterMeta?.label.toLowerCase()}
          </p>
        </div>
      </div>

      <div role="tabpanel">
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
          <>
            <div className="space-y-3 p-4 lg:hidden">
              {filtered.map((order) => (
                <OrderCard
                  key={order.id}
                  order={order}
                  onMarkPaid={() => markPaymentReceived(order.id)}
                  onMarkDelivered={() => markOrderDelivered(order.id)}
                  onStatusChange={(status) => updateOrderStatus(order.id, status)}
                />
              ))}
            </div>

            <div className="hidden overflow-x-auto lg:block">
              <table className="w-full min-w-[960px] text-left text-sm">
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
                      onMarkDelivered={() => markOrderDelivered(order.id)}
                      onStatusChange={(status) => updateOrderStatus(order.id, status)}
                    />
                  ))}
                </tbody>
              </table>
            </div>
          </>
        )}
      </div>
    </div>
  );
}

function OrderRow({
  order,
  onMarkPaid,
  onMarkDelivered,
  onStatusChange,
}: {
  order: Order;
  onMarkPaid: () => void;
  onMarkDelivered: () => void;
  onStatusChange: (status: OrderStatus) => void;
}) {
  const customerName = `${order.customer.firstName} ${order.customer.lastName}`.trim();
  const isPaid = order.paymentStatus === PAYMENT_STATUS.PAID;
  const isCancelled = order.status === ORDER_STATUS.CANCELLED;
  const isDelivered = order.status === ORDER_STATUS.DELIVERED;

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
        <p className="text-xs text-zinc-500">{order.customer.phone || order.customer.email}</p>
      </td>
      <td className="px-4 py-3 font-semibold text-zinc-900">
        {formatPrice(order.grandTotal ?? order.totals.grandTotal)}
      </td>
      <td className="px-4 py-3">
        <PaymentStatusBadge status={order.paymentStatus} size="sm" />
      </td>
      <td className="px-4 py-3">
        {isCancelled ? (
          <span className="inline-flex rounded-md bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-700">
            Cancelled
          </span>
        ) : (
          <OrderStatusSelect
            value={normalizeStatusForSelect(order.status)}
            onChange={onStatusChange}
            disabled={isDelivered}
          />
        )}
      </td>
      <td className="px-4 py-3">
        <div className="flex flex-wrap items-center justify-end gap-2">
          <Link
            href={`/admin/orders/${order.id}`}
            className="rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-600 transition hover:bg-zinc-50"
          >
            View
          </Link>
          {!isPaid && !isCancelled && (
            <button
              type="button"
              onClick={onMarkPaid}
              className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-emerald-700"
            >
              Mark paid
            </button>
          )}
          {!isDelivered && !isCancelled && (
            <button
              type="button"
              onClick={onMarkDelivered}
              className="rounded-lg bg-[#c9a227] px-3 py-1.5 text-xs font-semibold text-zinc-900 transition hover:bg-[#e8c547]"
            >
              Complete
            </button>
          )}
        </div>
      </td>
    </tr>
  );
}

function OrderCard({
  order,
  onMarkPaid,
  onMarkDelivered,
  onStatusChange,
}: {
  order: Order;
  onMarkPaid: () => void;
  onMarkDelivered: () => void;
  onStatusChange: (status: OrderStatus) => void;
}) {
  const customerName = `${order.customer.firstName} ${order.customer.lastName}`.trim();
  const isPaid = order.paymentStatus === PAYMENT_STATUS.PAID;
  const isCancelled = order.status === ORDER_STATUS.CANCELLED;
  const isDelivered = order.status === ORDER_STATUS.DELIVERED;

  return (
    <article className="rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div>
          <Link
            href={`/admin/orders/${order.id}`}
            className="font-mono text-sm font-bold text-zinc-900 hover:text-[#8b6914]"
          >
            {shortenOrderId(order.id)}
          </Link>
          <p className="mt-1 font-medium text-zinc-900">{customerName || "—"}</p>
          <p className="text-xs text-zinc-500">{formatOrderDate(order.createdAt)}</p>
        </div>
        <p className="text-lg font-bold text-zinc-900">
          {formatPrice(order.grandTotal ?? order.totals.grandTotal)}
        </p>
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-2">
        <PaymentStatusBadge status={order.paymentStatus} size="sm" />
        {!isCancelled ? (
          <OrderStatusSelect
            value={normalizeStatusForSelect(order.status)}
            onChange={onStatusChange}
            disabled={isDelivered}
          />
        ) : (
          <span className="text-xs font-semibold text-zinc-500">Cancelled</span>
        )}
      </div>

      <p className="mt-2 text-xs text-zinc-500">{getOrderShippingMethodLabel(order)}</p>

      <div className="mt-4 flex flex-wrap gap-2">
        <Link href={`/admin/orders/${order.id}`} className="admin-btn-secondary text-xs">
          View details
        </Link>
        {!isPaid && !isCancelled && (
          <button type="button" onClick={onMarkPaid} className="admin-btn-success text-xs">
            Mark paid
          </button>
        )}
        {!isDelivered && !isCancelled && (
          <button type="button" onClick={onMarkDelivered} className="admin-btn-primary text-xs">
            Complete
          </button>
        )}
      </div>
    </article>
  );
}
