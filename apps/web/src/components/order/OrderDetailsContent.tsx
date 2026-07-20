"use client";

import Link from "next/link";
import { useCallback, useEffect, useState } from "react";
import {
  CustomerOrderApiError,
  fetchCustomerOrder,
} from "@/lib/api/customer-orders";
import type { Order } from "@/lib/types/order";
import { getMethodByCode } from "@/lib/shipping/engine";
import { PAYMENT_METHOD_LABELS } from "@/lib/payment/constants";
import { OrderSummaryPayment } from "@/components/payment/OrderSummaryPayment";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { OrderItemsList } from "./OrderItemsList";
import { OrderTimeline } from "./OrderTimeline";
import { OrderStatusBadge } from "./OrderStatusBadge";
import { OrderDeliveryOptionPanel } from "./OrderDeliveryOptionPanel";
import { OrderShipmentTrackingPanel } from "./OrderShipmentTrackingPanel";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";
import {
  isAuthRequiredMessage,
  toFriendlyAuthMessage,
} from "@/lib/auth/friendly-auth-messages";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { EmptyState } from "@/components/ui/EmptyState";
import { ErrorState } from "@/components/ui/ErrorState";
import { Skeleton } from "@/components/ui/Skeleton";

interface OrderDetailsContentProps {
  orderNumber: string;
}

function formatOrderDate(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "long",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

function formatAddressLines(order: Order): string[] {
  const address = order.shippingAddress;
  const lines = [
    address.addressLine1,
    address.addressLine2,
    [address.city, address.region].filter(Boolean).join(", "),
    address.postalCode,
    address.country,
  ].filter((line): line is string => Boolean(line && line.trim()));

  return lines.length > 0 ? lines : ["Address details will appear when available."];
}

function OrderDetailsSkeleton() {
  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8" aria-busy="true">
      <Skeleton className="h-4 w-40" />
      <Skeleton className="mt-4 h-9 w-56" rounded="lg" />
      <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_340px]">
        <div className="space-y-6">
          <Skeleton className="h-72 w-full" rounded="3xl" />
          <Skeleton className="h-48 w-full" rounded="3xl" />
          <Skeleton className="h-40 w-full" rounded="3xl" />
        </div>
        <Skeleton className="h-80 w-full" rounded="3xl" />
      </div>
    </div>
  );
}

export function OrderDetailsContent({ orderNumber }: OrderDetailsContentProps) {
  const [order, setOrder] = useState<Order | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [needsAuth, setNeedsAuth] = useState(false);

  const loadOrder = useCallback(async () => {
    setIsLoading(true);
    setErrorMessage(null);
    setNeedsAuth(false);
    setOrder(null);

    if (!getCustomerApiToken()) {
      setNeedsAuth(true);
      setIsLoading(false);
      return;
    }

    try {
      const loaded = await fetchCustomerOrder(orderNumber);
      setOrder(loaded);
    } catch (error) {
      if (error instanceof CustomerOrderApiError && error.statusCode === 404) {
        setOrder(null);
        setErrorMessage(null);
      } else if (error instanceof CustomerOrderApiError) {
        if (isAuthRequiredMessage(error.message) || error.statusCode === 401) {
          setNeedsAuth(true);
        } else {
          setErrorMessage(toFriendlyAuthMessage(error.message, error.message));
        }
      } else if (error instanceof Error) {
        if (isAuthRequiredMessage(error.message)) {
          setNeedsAuth(true);
        } else {
          setErrorMessage(error.message);
        }
      } else {
        setErrorMessage("Unable to load this order.");
      }
    } finally {
      setIsLoading(false);
    }
  }, [orderNumber]);

  useEffect(() => {
    void loadOrder();
  }, [loadOrder]);

  if (isLoading) {
    return <OrderDetailsSkeleton />;
  }

  if (needsAuth) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
        <AuthInvitationCard
          context="orders"
          returnUrl={`/orders/${encodeURIComponent(orderNumber)}`}
        />
      </div>
    );
  }

  if (errorMessage) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <ErrorState message={errorMessage} onRetry={() => void loadOrder()} />
        <div className="mt-4 text-center">
          <Link
            href="/orders"
            className="text-sm font-semibold text-[#8b6914] hover:text-[#c9a227]"
          >
            View My Orders
          </Link>
        </div>
      </div>
    );
  }

  if (!order) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6">
        <EmptyState
          icon="📦"
          title="Order not found"
          description={`We couldn't find order ${orderNumber}.`}
          primaryAction={{ label: "View My Orders", href: "/orders" }}
          secondaryAction={{ label: "Continue Shopping", href: "/products" }}
        />
      </div>
    );
  }

  const shippingMethods = [...new Set(order.items.map((item) => item.shippingMethod))];
  const paymentLabel =
    (order.paymentMethod && PAYMENT_METHOD_LABELS[order.paymentMethod]) ||
    order.paymentMethod ||
    "—";
  const addressLines = formatAddressLines(order);
  const trackHref = `/track/${encodeURIComponent(order.id)}`;

  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
      <nav aria-label="Breadcrumb" className="mb-6">
        <ol className="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
          <li>
            <Link href="/account" className="font-medium transition hover:text-[#8b6914]">
              Account
            </Link>
          </li>
          <li aria-hidden>/</li>
          <li>
            <Link href="/orders" className="font-medium transition hover:text-[#8b6914]">
              My Orders
            </Link>
          </li>
          <li aria-hidden>/</li>
          <li className="font-mono font-semibold text-zinc-900">{order.orderNumber}</li>
        </ol>
      </nav>

      <header className="relative overflow-hidden rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7">
        <div
          className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_rgba(201,162,39,0.1),_transparent_50%)]"
          aria-hidden
        />
        <div className="relative flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
              Order Details
            </p>
            <h1 className="mt-1.5 font-mono text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
              {order.orderNumber}
            </h1>
            <p className="mt-2 text-sm leading-relaxed text-zinc-500">
              Placed {formatOrderDate(order.createdAt)}
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <PaymentStatusBadge status={order.paymentStatus} />
            <OrderStatusBadge status={order.status} />
          </div>
        </div>

        <div className="relative mt-5 flex flex-wrap gap-2">
          {(order.status === "pending_payment" || order.status === "pending") && (
            <Link
              href={`/orders/${encodeURIComponent(order.orderNumber)}/pay`}
              className="inline-flex min-h-10 items-center justify-center rounded-xl bg-zinc-900 px-4 text-sm font-bold text-white transition hover:bg-zinc-800"
            >
              Pay now
            </Link>
          )}
          <Link
            href={trackHref}
            className="inline-flex min-h-10 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-4 text-sm font-bold text-zinc-900 shadow-sm transition hover:brightness-105"
          >
            Track Order
          </Link>
          {(order.status === "delivered" || order.status === "completed") && (
            <Link
              href={`/orders/${encodeURIComponent(order.orderNumber)}/return`}
              className="inline-flex min-h-10 items-center justify-center rounded-xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-800 transition hover:border-[#c9a227]/50"
            >
              Request return
            </Link>
          )}
          <button
            type="button"
            disabled
            title="Buy Again will be available soon"
            className="inline-flex min-h-10 cursor-not-allowed items-center justify-center rounded-xl border border-zinc-100 bg-zinc-50 px-4 text-sm font-semibold text-zinc-400"
          >
            Buy Again
          </button>
        </div>
      </header>

      <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
        <div className="space-y-6">
          <section
            aria-labelledby="timeline-heading"
            className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7"
          >
            <h2 id="timeline-heading" className="text-lg font-bold text-zinc-900">
              Order progress
            </h2>
            <p className="mt-1 text-sm text-zinc-500">Follow every step from placement to delivery.</p>
            <div className="mt-6">
              <OrderTimeline events={order.timeline} />
            </div>
          </section>

          <section
            aria-labelledby="items-heading"
            className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7"
          >
            <h2 id="items-heading" className="text-lg font-bold text-zinc-900">
              Items
            </h2>
            <div className="mt-6">
              <OrderItemsList items={order.items} />
            </div>
          </section>

          <OrderDeliveryOptionPanel
            orderNumber={order.orderNumber}
            canSelect={
              order.paymentStatus === "paid" ||
              ["confirmed", "processing", "shipped", "paid"].includes(order.status)
            }
          />

          <OrderShipmentTrackingPanel orderNumber={order.orderNumber} />

          <section
            aria-labelledby="shipping-details-heading"
            className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7"
          >
            <h2 id="shipping-details-heading" className="text-lg font-bold text-zinc-900">
              Shipping & payment
            </h2>
            <div className="mt-6 grid gap-6 sm:grid-cols-2">
              <div>
                <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
                  Shipping Address
                </p>
                <div className="mt-3 space-y-1 text-sm leading-relaxed text-zinc-700">
                  {addressLines.map((line) => (
                    <p key={line}>{line}</p>
                  ))}
                </div>
              </div>

              <div>
                <p className="text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
                  Shipping Method
                </p>
                <div className="mt-3 space-y-1.5">
                  {shippingMethods.length > 0 ? (
                    shippingMethods.map((code) => {
                      const method = getMethodByCode(code);
                      return (
                        <p key={code} className="text-sm font-medium text-zinc-900">
                          {method ? `${method.icon} ${method.name}` : code}
                        </p>
                      );
                    })
                  ) : (
                    <p className="text-sm text-zinc-500">—</p>
                  )}
                </div>

                <p className="mt-5 text-[11px] font-bold uppercase tracking-[0.12em] text-zinc-500">
                  Payment Method
                </p>
                <p className="mt-3 text-sm font-medium text-zinc-900">{paymentLabel}</p>
              </div>
            </div>
          </section>
        </div>

        <aside className="lg:sticky lg:top-24 lg:self-start">
          <div className="rounded-3xl border border-zinc-200/80 bg-white p-5 shadow-[0_4px_24px_rgba(0,0,0,0.06)] sm:p-6">
            <h2 className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500">
              Totals
            </h2>
            <div className="mt-5">
              <OrderSummaryPayment
                totals={order.totals}
                paymentStatus={order.paymentStatus}
                paymentMethod={order.paymentMethod ?? undefined}
                paymentReference={order.paymentReference}
              />
            </div>

            <div className="mt-5 flex flex-col gap-2">
              <Link
                href="/orders"
                className="block text-center text-sm font-semibold text-[#8b6914] transition hover:text-[#c9a227]"
              >
                View My Orders
              </Link>
              <Link
                href="/products"
                className="block text-center text-sm font-medium text-zinc-500 transition hover:text-zinc-700"
              >
                Continue Shopping
              </Link>
            </div>
          </div>
        </aside>
      </div>
    </div>
  );
}
