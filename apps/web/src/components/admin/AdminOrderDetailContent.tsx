"use client";

import Link from "next/link";
import { useMemo } from "react";
import { formatPrice } from "@/lib/catalog/utils";
import { getOrderShippingMethodLabel } from "@/lib/payment/order-filters";
import { PAYMENT_METHOD_LABELS } from "@/lib/payment/constants";
import {
  resolveAdminPaymentStatusLabel,
  resolveAdminFulfilmentStatusLabel,
  ADMIN_SHIPPING_CONFIGURATION_MESSAGE,
} from "@/lib/admin/order-detail-display";
import { resolveAdminOrderSourceBadge } from "@/lib/admin/order-source-badge";
import { useAdminOrders } from "@/components/admin/AdminOrdersProvider";
import { AdminConfirmOfficePaymentCard } from "@/components/admin/AdminConfirmOfficePaymentCard";
import { useAdminPermissions } from "@/hooks/use-admin-permissions";
import { AdminOrderCustomerCard } from "@/components/admin/AdminOrderCustomerCard";
import { AdminOrderFulfilmentWorkspaceCard } from "@/components/admin/AdminOrderFulfilmentWorkspaceCard";
import { AdminOrderItemsList } from "@/components/admin/AdminOrderItemsList";
import { AdminOrderSourceBadge } from "@/components/admin/AdminOrderSourceBadge";
import { AdminOrderStatusOverview } from "@/components/admin/AdminOrderStatusOverview";
import { OrderSummaryPayment } from "@/components/payment/OrderSummaryPayment";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { OrderLiveStatusIndicator } from "@/components/admin/OrderLiveStatusIndicator";
import { OrderCustomerDetails } from "@/components/order/OrderCustomerDetails";
import { ShippingBreakdownList } from "@/components/shipping/ShippingQuantityBreakdown";
import { getMethodByCode } from "@/lib/shipping/engine";

interface AdminOrderDetailContentProps {
  orderId: string;
}

export function AdminOrderDetailContent({ orderId }: AdminOrderDetailContentProps) {
  const { getOrderById, isHydrated, refreshOrders } = useAdminOrders();
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const order = getOrderById(orderId);

  const shippingMethods = useMemo(() => {
    if (!order) return [];
    return [...new Set(order.items.map((item) => item.shippingMethod))];
  }, [order]);

  if (!isHydrated) {
    return (
      <div className="p-4 sm:p-6 lg:p-8" aria-busy="true">
        <div className="h-8 w-48 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-6 h-20 animate-pulse rounded-xl bg-zinc-50" />
        <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
          <div className="h-96 animate-pulse rounded-xl bg-zinc-50" />
          <div className="h-64 animate-pulse rounded-xl bg-zinc-50" />
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

  const sourceBadge = resolveAdminOrderSourceBadge(order);

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

      <header className="admin-card mt-4 p-4 sm:p-5">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="font-mono text-lg font-bold text-zinc-900 sm:text-xl">
                {order.orderNumber}
              </h1>
              <AdminOrderSourceBadge badge={sourceBadge} />
            </div>
            <p className="mt-0.5 font-mono text-[11px] text-zinc-400">ID: {order.id}</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <OrderLiveStatusIndicator order={order} showLabel />
            <PaymentStatusBadge status={order.paymentStatus} />
            {order.paymentMethod ? (
              <span className="rounded-md bg-zinc-100 px-2 py-1 text-[11px] font-semibold text-zinc-700">
                {PAYMENT_METHOD_LABELS[order.paymentMethod] ?? order.paymentMethod}
              </span>
            ) : null}
          </div>
        </div>

        <dl className="mt-4 grid gap-3 border-t border-zinc-100 pt-4 sm:grid-cols-2">
          <div>
            <dt className="text-[10px] font-bold uppercase tracking-[0.12em] text-zinc-400">
              Payment Status
            </dt>
            <dd className="mt-1 text-sm font-semibold text-zinc-900">
              {resolveAdminPaymentStatusLabel(order)}
            </dd>
          </div>
          <div>
            <dt className="text-[10px] font-bold uppercase tracking-[0.12em] text-zinc-400">
              Fulfilment Status
            </dt>
            <dd className="mt-1 text-sm font-semibold text-zinc-900">
              {resolveAdminFulfilmentStatusLabel(order)}
            </dd>
          </div>
        </dl>

        <div className="mt-4 border-t border-zinc-100 pt-4">
          <AdminOrderCustomerCard customer={order.customer} orderDate={order.createdAt} />
        </div>
      </header>

      <section className="admin-card mt-4 p-4 sm:p-5">
        <h2 className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500">
          Order status overview
        </h2>
        <div className="mt-4">
          <AdminOrderStatusOverview order={order} />
        </div>
      </section>

      <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start lg:gap-6">
        <div className="space-y-4">
          <section className="admin-card p-4 sm:p-5">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-sm font-semibold text-zinc-900">
                Products ({order.totals.itemCount})
              </h2>
              <p className="text-xs text-zinc-500">Checkout snapshot</p>
            </div>
            <div className="mt-3">
              <AdminOrderItemsList order={order} items={order.items} />
            </div>
          </section>

          <section className="admin-card p-4 sm:p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Shipping</h2>
            <dl className="mt-3 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-[10px] font-bold uppercase tracking-[0.12em] text-zinc-400">
                  Method
                </dt>
                <dd className="mt-1 font-medium text-zinc-900">
                  {getOrderShippingMethodLabel(order)}
                </dd>
                {shippingMethods.length > 1 && (
                  <dd className="mt-1.5 flex flex-wrap gap-1.5">
                    {shippingMethods.map((code) => {
                      const method = getMethodByCode(code);
                      return (
                        <span
                          key={code}
                          className="inline-flex items-center gap-1 rounded-md bg-zinc-100 px-2 py-0.5 text-[11px] font-medium text-zinc-700"
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
                <dt className="text-[10px] font-bold uppercase tracking-[0.12em] text-zinc-400">
                  Shipping cost
                </dt>
                <dd className="mt-1 font-semibold text-zinc-900">
                  {formatPrice(order.shippingTotal ?? order.totals.shippingTotal)}
                </dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-[10px] font-bold uppercase tracking-[0.12em] text-zinc-400">
                  Delivery timing
                </dt>
                <dd className="mt-1 text-sm text-zinc-600">
                  {ADMIN_SHIPPING_CONFIGURATION_MESSAGE}
                </dd>
              </div>
            </dl>

            {order.itemShippingBreakdown && order.itemShippingBreakdown.length > 0 && (
              <div className="mt-4 rounded-xl border border-zinc-100 bg-zinc-50/80 p-3">
                <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-zinc-400">
                  Per-line shipping
                </p>
                <ShippingBreakdownList rows={order.itemShippingBreakdown} className="mt-2" />
              </div>
            )}
          </section>

          <section className="admin-card p-4 sm:p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Customer & address</h2>
            <div className="mt-3">
              <OrderCustomerDetails
                customer={order.customer}
                shippingAddress={order.shippingAddress}
                orderNotes={order.orderNotes}
              />
            </div>
          </section>
        </div>

        <aside className="space-y-4 lg:sticky lg:top-20 lg:self-start">
          <AdminOrderFulfilmentWorkspaceCard orderId={order.id} />

          <section className="admin-card p-4 sm:p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Reference links</h2>
            <div className="mt-3 flex flex-col gap-2">
              <Link
                href={`/track/${order.id}`}
                target="_blank"
                rel="noopener noreferrer"
                className="rounded-lg border border-zinc-200 px-3 py-2 text-center text-xs font-semibold text-zinc-600 transition hover:bg-zinc-50"
              >
                Customer track page
              </Link>
              <Link
                href={`/order-success/${order.id}`}
                target="_blank"
                rel="noopener noreferrer"
                className="rounded-lg border border-zinc-200 px-3 py-2 text-center text-xs font-semibold text-zinc-600 transition hover:bg-zinc-50"
              >
                Confirmation page
              </Link>
            </div>
          </section>

          <AdminConfirmOfficePaymentCard
            order={order}
            permissions={permissions}
            permissionsLoading={permissionsLoading}
            onConfirmed={refreshOrders}
          />

          <section className="admin-card p-4 sm:p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Payment summary</h2>
            <div className="mt-3">
              <OrderSummaryPayment
                totals={order.totals}
                paymentStatus={order.paymentStatus}
                paymentMethod={order.paymentMethod ?? undefined}
                paymentReference={order.paymentReference}
              />
            </div>
            <p className="mt-3 text-lg font-bold text-zinc-900">
              {formatPrice(order.grandTotal ?? order.totals.grandTotal)}
            </p>
          </section>
        </aside>
      </div>
    </div>
  );
}
