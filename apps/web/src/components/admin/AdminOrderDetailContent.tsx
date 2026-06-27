"use client";

import Link from "next/link";
import { useMemo } from "react";
import type { Order } from "@/lib/types/order";
import { ORDER_STATUS } from "@/lib/types/order";
import { formatDeliveryEstimate, formatPrice } from "@/lib/catalog/utils";
import {
  getOrderFulfillmentLabel,
  getOrderShippingMethodLabel,
} from "@/lib/payment/order-filters";
import { PAYMENT_METHOD_LABELS } from "@/lib/payment/constants";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import { getMethodByCode } from "@/lib/shipping/engine";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";
import { OrderStatusSelect } from "@/components/admin/OrderStatusSelect";
import { OrderSummaryPayment } from "@/components/payment/OrderSummaryPayment";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { OrderCustomerDetails } from "@/components/order/OrderCustomerDetails";
import { OrderItemsList } from "@/components/order/OrderItemsList";
import { OrderTimeline } from "@/components/order/OrderTimeline";
import { ShippingBreakdownList } from "@/components/shipping/ShippingQuantityBreakdown";

interface AdminOrderDetailContentProps {
  orderId: string;
}

function formatOrderDate(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "long",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

function canManageOrder(order: Order): boolean {
  return order.status !== ORDER_STATUS.CANCELLED && order.status !== ORDER_STATUS.DELIVERED;
}

export function AdminOrderDetailContent({ orderId }: AdminOrderDetailContentProps) {
  const {
    getOrderById,
    isHydrated,
    markPaymentReceived,
    markOrderProcessing,
    markOrderShipped,
    markOrderDelivered,
    updateOrderStatus,
  } = useAdminOrders();
  const order = getOrderById(orderId);

  const shippingMethods = useMemo(() => {
    if (!order) return [];
    return [...new Set(order.items.map((item) => item.shippingMethod))];
  }, [order]);

  const deliveryEstimates = useMemo(() => {
    if (!order) return [];
    return [
      ...new Set(
        order.items
          .map((item) => item.estimatedDeliveryDays ?? item.shipping?.days)
          .filter((value) => value && value !== "—"),
      ),
    ];
  }, [order]);

  if (!isHydrated) {
    return (
      <div className="p-4 sm:p-6 lg:p-8" aria-busy="true">
        <div className="h-8 w-48 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_360px]">
          <div className="h-96 animate-pulse rounded-xl bg-zinc-50" />
          <div className="h-80 animate-pulse rounded-xl bg-zinc-50" />
        </div>
      </div>
    );
  }

  if (!order) {
    return (
      <div className="p-4 sm:p-6 lg:p-8">
        <Link
          href="/admin/orders"
          className="text-sm font-medium text-zinc-500 transition hover:text-zinc-900"
        >
          ← Back to orders
        </Link>
        <div className="admin-card mt-8 p-12 text-center">
          <p className="text-sm font-medium text-zinc-700">Order not found</p>
          <p className="mt-1 text-xs text-zinc-500">
            No order matches ID <span className="font-mono">{orderId}</span>.
          </p>
        </div>
      </div>
    );
  }

  const customerName = `${order.customer.firstName} ${order.customer.lastName}`.trim();
  const editable = canManageOrder(order);
  const isPaid = order.paymentStatus === PAYMENT_STATUS.PAID;

  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <nav aria-label="Breadcrumb">
        <Link
          href="/admin/orders"
          className="text-sm font-medium text-zinc-500 transition hover:text-zinc-900"
        >
          ← Back to orders
        </Link>
      </nav>

      <header className="mt-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <p className="text-xs font-medium uppercase tracking-wide text-zinc-500">Order details</p>
          <h1 className="mt-1 font-mono text-xl font-semibold text-zinc-900 sm:text-2xl">
            {order.orderNumber}
          </h1>
          <p className="mt-1 font-mono text-xs text-zinc-400">ID: {order.id}</p>
          <p className="mt-1 text-sm text-zinc-500">
            {customerName} · Placed {formatOrderDate(order.createdAt)}
          </p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <span className="inline-flex rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">
            {getOrderFulfillmentLabel(order.status)}
          </span>
          <PaymentStatusBadge status={order.paymentStatus} />
          {order.paymentMethod ? (
            <span className="rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-700">
              {PAYMENT_METHOD_LABELS[order.paymentMethod] ?? order.paymentMethod}
            </span>
          ) : null}
        </div>
      </header>

      <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        <div className="space-y-6">
          <section className="admin-card p-5 sm:p-6">
            <h2 className="text-sm font-semibold text-zinc-900">Customer</h2>
            <div className="mt-4">
              <OrderCustomerDetails
                customer={order.customer}
                shippingAddress={order.shippingAddress}
                orderNotes={order.orderNotes}
              />
            </div>
          </section>

          <section className="admin-card p-5 sm:p-6">
            <h2 className="text-sm font-semibold text-zinc-900">Items (frozen snapshot)</h2>
            <p className="mt-1 text-xs text-zinc-500">
              Prices, variants, and shipping captured at checkout — not recalculated.
            </p>
            <div className="mt-4">
              <OrderItemsList items={order.items} />
            </div>
          </section>

          <section className="admin-card p-5 sm:p-6">
            <h2 className="text-sm font-semibold text-zinc-900">Shipping</h2>
            <dl className="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <dt className="text-xs font-medium uppercase tracking-wide text-zinc-500">
                  Shipping method
                </dt>
                <dd className="mt-1.5 text-sm font-medium text-zinc-900">
                  {getOrderShippingMethodLabel(order)}
                </dd>
                {shippingMethods.length > 1 && (
                  <dd className="mt-2 flex flex-wrap gap-2">
                    {shippingMethods.map((code) => {
                      const method = getMethodByCode(code);
                      return (
                        <span
                          key={code}
                          className="inline-flex items-center gap-1 rounded-lg bg-zinc-100 px-2 py-1 text-xs font-medium text-zinc-800"
                        >
                          <span aria-hidden>{method?.icon}</span>
                          {method?.name ?? code}
                        </span>
                      );
                    })}
                  </dd>
                )}
              </div>
              <div>
                <dt className="text-xs font-medium uppercase tracking-wide text-zinc-500">
                  Delivery estimate
                </dt>
                <dd className="mt-1.5 text-sm font-medium text-zinc-900">
                  {deliveryEstimates.length > 0
                    ? deliveryEstimates.map((estimate) => formatDeliveryEstimate(estimate)).join(", ")
                    : "—"}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-medium uppercase tracking-wide text-zinc-500">
                  Shipping total
                </dt>
                <dd className="mt-1.5 text-sm font-semibold text-zinc-900">
                  {formatPrice(order.shippingTotal ?? order.totals.shippingTotal)}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-medium uppercase tracking-wide text-zinc-500">
                  Item count
                </dt>
                <dd className="mt-1.5 text-sm font-medium text-zinc-900">
                  {order.totals.itemCount} item{order.totals.itemCount === 1 ? "" : "s"}
                </dd>
              </div>
            </dl>

            {order.itemShippingBreakdown && order.itemShippingBreakdown.length > 0 && (
              <div className="mt-5 rounded-xl border border-zinc-100 bg-zinc-50/80 p-4">
                <p className="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-500">
                  Per-line shipping breakdown
                </p>
                <ShippingBreakdownList rows={order.itemShippingBreakdown} className="mt-2" />
              </div>
            )}
          </section>

          <section className="admin-card p-5 sm:p-6">
            <h2 className="text-sm font-semibold text-zinc-900">Order timeline</h2>
            <div className="mt-4">
              <OrderTimeline events={order.timeline} />
            </div>
          </section>
        </div>

        <aside className="space-y-6 lg:sticky lg:top-24 lg:self-start">
          <section className="admin-card p-5 sm:p-6">
            <h2 className="text-sm font-semibold text-zinc-900">Order actions</h2>

            {editable ? (
              <div className="mt-4 space-y-4">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wide text-zinc-500">
                    Update order status
                  </p>
                  <div className="mt-2">
                    <OrderStatusSelect
                      value={
                        order.status === ORDER_STATUS.CONFIRMED ||
                        order.status === ORDER_STATUS.PENDING_PAYMENT
                          ? ORDER_STATUS.PENDING
                          : order.status === ORDER_STATUS.CANCELLED
                            ? ORDER_STATUS.PENDING
                            : order.status
                      }
                      onChange={(status) => updateOrderStatus(order.id, status)}
                      className="w-full text-sm"
                    />
                  </div>
                </div>

                <div className="flex flex-col gap-2">
                  {!isPaid && (
                    <ActionButton
                      label="Mark as Paid"
                      description="Manual payment override"
                      onClick={() => markPaymentReceived(order.id)}
                      variant="success"
                    />
                  )}
                  {order.status !== ORDER_STATUS.PROCESSING &&
                    order.status !== ORDER_STATUS.SHIPPED &&
                    order.status !== ORDER_STATUS.DELIVERED && (
                      <ActionButton
                        label="Mark as Processing"
                        description="Start preparing the order"
                        onClick={() => markOrderProcessing(order.id)}
                      />
                    )}
                  {order.status !== ORDER_STATUS.SHIPPED && order.status !== ORDER_STATUS.DELIVERED && (
                    <ActionButton
                      label="Mark as Shipped"
                      description="Order is in transit"
                      onClick={() => markOrderShipped(order.id)}
                    />
                  )}
                  {order.status !== ORDER_STATUS.DELIVERED && (
                    <ActionButton
                      label="Mark as Completed"
                      description="Order delivered to customer"
                      onClick={() => markOrderDelivered(order.id)}
                      variant="muted"
                    />
                  )}
                </div>
              </div>
            ) : (
              <p className="mt-4 text-sm text-zinc-500">
                {order.status === ORDER_STATUS.DELIVERED
                  ? "This order has been delivered."
                  : "This order was cancelled."}
              </p>
            )}

            <div className="mt-4 flex flex-col gap-2 border-t border-zinc-100 pt-4">
              <Link
                href={`/track-order/${order.id}`}
                target="_blank"
                rel="noopener noreferrer"
                className="rounded-lg border border-zinc-200 px-4 py-2.5 text-center text-sm font-semibold text-zinc-600 transition hover:bg-zinc-50"
              >
                View customer track page
              </Link>
              <Link
                href={`/order-success/${order.id}`}
                target="_blank"
                rel="noopener noreferrer"
                className="rounded-lg border border-zinc-200 px-4 py-2.5 text-center text-sm font-semibold text-zinc-600 transition hover:bg-zinc-50"
              >
                View confirmation page
              </Link>
            </div>
          </section>

          <section className="admin-card p-5 sm:p-6">
            <h2 className="text-sm font-semibold text-zinc-900">Payment summary (snapshot)</h2>
            <div className="mt-4">
              <OrderSummaryPayment
                totals={order.totals}
                paymentStatus={order.paymentStatus}
                paymentMethod={order.paymentMethod ?? undefined}
                paymentReference={order.paymentReference}
              />
            </div>
          </section>
        </aside>
      </div>
    </div>
  );
}

function ActionButton({
  label,
  description,
  onClick,
  variant = "primary",
}: {
  label: string;
  description: string;
  onClick: () => void;
  variant?: "primary" | "success" | "muted";
}) {
  const styles = {
    primary: "bg-[#c9a227] text-zinc-900 hover:bg-[#e8c547]",
    success: "bg-emerald-600 text-white hover:bg-emerald-700",
    muted: "border border-zinc-200 bg-white text-zinc-700 hover:bg-zinc-50",
  };

  return (
    <button
      type="button"
      onClick={onClick}
      className={`w-full rounded-lg px-4 py-3 text-left transition ${styles[variant]}`}
    >
      <span className="block text-sm font-semibold">{label}</span>
      <span className={`mt-0.5 block text-xs ${variant === "success" ? "text-emerald-100" : "text-zinc-500"}`}>
        {description}
      </span>
    </button>
  );
}
