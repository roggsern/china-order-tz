"use client";

import Link from "next/link";
import { useCallback, useEffect, useMemo, useState } from "react";
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
import { AdminOrdersApiError, fetchAdminOrderById } from "@/lib/api/admin-orders";
import type { Order } from "@/lib/types/order";

interface AdminOrderDetailContentProps {
  orderId: string;
}

type DetailLoadState = "loading" | "found" | "not_found" | "error";

export function AdminOrderDetailContent({ orderId }: AdminOrderDetailContentProps) {
  const { getOrderById, refreshOrders } = useAdminOrders();
  const { permissions, loading: permissionsLoading } = useAdminPermissions();
  const cachedOrder = getOrderById(orderId);
  const [order, setOrder] = useState<Order | null>(null);
  const [loadState, setLoadState] = useState<DetailLoadState>("loading");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const loadOrder = useCallback(async () => {
    setLoadState("loading");
    setErrorMessage(null);
    try {
      const fetched = await fetchAdminOrderById(orderId);
      setOrder(fetched);
      setLoadState("found");
    } catch (error) {
      if (error instanceof AdminOrdersApiError && error.statusCode === 404) {
        setOrder(null);
        setLoadState("not_found");
        return;
      }
      setLoadState("error");
      setErrorMessage(
        error instanceof AdminOrdersApiError ? error.message : "Unable to load order.",
      );
    }
  }, [orderId]);

  useEffect(() => {
    void loadOrder();
  }, [loadOrder]);

  const handleConfirmed = useCallback(() => {
    void loadOrder();
    refreshOrders();
  }, [loadOrder, refreshOrders]);

  const displayOrder = order ?? (loadState === "loading" || loadState === "error" ? cachedOrder ?? null : null);

  const shippingMethods = useMemo(() => {
    if (!displayOrder) return [];
    return [...new Set(displayOrder.items.map((item) => item.shippingMethod))];
  }, [displayOrder]);

  if (loadState === "loading" && !displayOrder) {
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

  if (loadState === "not_found") {
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

  if (loadState === "error" && !displayOrder) {
    return (
      <div className="p-4 sm:p-6 lg:p-8">
        <Link
          href="/admin/orders"
          className="text-sm font-medium text-zinc-500 transition hover:text-zinc-900"
        >
          ← Back to orders
        </Link>
        <div className="admin-card mt-8 p-12 text-center">
          <p className="text-sm font-medium text-zinc-700">Unable to load order</p>
          <p className="mt-1 text-xs text-zinc-500">{errorMessage ?? "Please try again."}</p>
          <button
            type="button"
            onClick={() => void loadOrder()}
            className="mt-4 inline-flex rounded-lg border border-zinc-200 px-3 py-1.5 text-xs font-semibold text-zinc-700 hover:bg-zinc-50"
          >
            Retry
          </button>
        </div>
      </div>
    );
  }

  if (!displayOrder) {
    return (
      <div className="p-4 sm:p-6 lg:p-8" aria-busy="true">
        <div className="h-8 w-48 animate-pulse rounded-lg bg-zinc-100" />
      </div>
    );
  }

  const sourceBadge = resolveAdminOrderSourceBadge(displayOrder);

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

      {loadState === "error" ? (
        <p className="admin-card mt-4 p-3 text-sm text-amber-800" role="alert">
          {errorMessage ?? "Unable to refresh this order. Showing the last loaded copy."}
        </p>
      ) : null}

      <header className="admin-card mt-4 p-4 sm:p-5">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="font-mono text-lg font-bold text-zinc-900 sm:text-xl">
                {displayOrder.orderNumber}
              </h1>
              <AdminOrderSourceBadge badge={sourceBadge} />
            </div>
            <p className="mt-0.5 font-mono text-[11px] text-zinc-400">ID: {displayOrder.id}</p>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <OrderLiveStatusIndicator order={displayOrder} showLabel />
            <PaymentStatusBadge status={displayOrder.paymentStatus} />
            {displayOrder.paymentMethod ? (
              <span className="rounded-md bg-zinc-100 px-2 py-1 text-[11px] font-semibold text-zinc-700">
                {PAYMENT_METHOD_LABELS[displayOrder.paymentMethod] ?? displayOrder.paymentMethod}
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
              {resolveAdminPaymentStatusLabel(displayOrder)}
            </dd>
          </div>
          <div>
            <dt className="text-[10px] font-bold uppercase tracking-[0.12em] text-zinc-400">
              Fulfilment Status
            </dt>
            <dd className="mt-1 text-sm font-semibold text-zinc-900">
              {resolveAdminFulfilmentStatusLabel(displayOrder)}
            </dd>
          </div>
        </dl>

        <div className="mt-4 border-t border-zinc-100 pt-4">
          <AdminOrderCustomerCard customer={displayOrder.customer} orderDate={displayOrder.createdAt} />
        </div>
      </header>

      <section className="admin-card mt-4 p-4 sm:p-5">
        <h2 className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500">
          Order status overview
        </h2>
        <div className="mt-4">
          <AdminOrderStatusOverview order={displayOrder} />
        </div>
      </section>

      <div className="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start lg:gap-6">
        <div className="space-y-4">
          <section className="admin-card p-4 sm:p-5">
            <div className="flex items-center justify-between gap-3">
              <h2 className="text-sm font-semibold text-zinc-900">
                Products ({displayOrder.totals.itemCount})
              </h2>
              <p className="text-xs text-zinc-500">Checkout snapshot</p>
            </div>
            <div className="mt-3">
              <AdminOrderItemsList order={displayOrder} items={displayOrder.items} />
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
                  {getOrderShippingMethodLabel(displayOrder)}
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
                  {formatPrice(displayOrder.shippingTotal ?? displayOrder.totals.shippingTotal)}
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

            {displayOrder.itemShippingBreakdown && displayOrder.itemShippingBreakdown.length > 0 && (
              <div className="mt-4 rounded-xl border border-zinc-100 bg-zinc-50/80 p-3">
                <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-zinc-400">
                  Per-line shipping
                </p>
                <ShippingBreakdownList rows={displayOrder.itemShippingBreakdown} className="mt-2" />
              </div>
            )}
          </section>

          <section className="admin-card p-4 sm:p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Customer & address</h2>
            <div className="mt-3">
              <OrderCustomerDetails
                customer={displayOrder.customer}
                shippingAddress={displayOrder.shippingAddress}
                orderNotes={displayOrder.orderNotes}
              />
            </div>
          </section>
        </div>

        <aside className="space-y-4 lg:sticky lg:top-20 lg:self-start">
          <AdminOrderFulfilmentWorkspaceCard orderId={displayOrder.id} />

          <section className="admin-card p-4 sm:p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Reference links</h2>
            <div className="mt-3 flex flex-col gap-2">
              <Link
                href={`/track/${displayOrder.id}`}
                target="_blank"
                rel="noopener noreferrer"
                className="rounded-lg border border-zinc-200 px-3 py-2 text-center text-xs font-semibold text-zinc-600 transition hover:bg-zinc-50"
              >
                Customer track page
              </Link>
              <Link
                href={`/order-success/${displayOrder.id}`}
                target="_blank"
                rel="noopener noreferrer"
                className="rounded-lg border border-zinc-200 px-3 py-2 text-center text-xs font-semibold text-zinc-600 transition hover:bg-zinc-50"
              >
                Confirmation page
              </Link>
            </div>
          </section>

          <AdminConfirmOfficePaymentCard
            order={displayOrder}
            permissions={permissions}
            permissionsLoading={permissionsLoading}
            onConfirmed={handleConfirmed}
          />

          <section className="admin-card p-4 sm:p-5">
            <h2 className="text-sm font-semibold text-zinc-900">Payment summary</h2>
            <div className="mt-3">
              <OrderSummaryPayment
                totals={displayOrder.totals}
                paymentStatus={displayOrder.paymentStatus}
                paymentMethod={displayOrder.paymentMethod ?? undefined}
                paymentReference={displayOrder.paymentReference}
              />
            </div>
            <p className="mt-3 text-lg font-bold text-zinc-900">
              {formatPrice(displayOrder.grandTotal ?? displayOrder.totals.grandTotal)}
            </p>
          </section>
        </aside>
      </div>
    </div>
  );
}
