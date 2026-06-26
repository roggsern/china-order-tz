"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import type { Order } from "@/lib/types/order";
import { paymentService } from "@/lib/payment/PaymentService";
import { OrderSummaryPayment } from "@/components/payment/OrderSummaryPayment";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { OrderItemsList } from "./OrderItemsList";
import { OrderCustomerDetails } from "./OrderCustomerDetails";
import { OrderTimeline } from "./OrderTimeline";
import { OrderStatusBadge } from "./OrderStatusBadge";
import { ShippingStatusSummary } from "./ShippingStatusSummary";

interface OrderDetailsContentProps {
  orderNumber: string;
}

function formatOrderDate(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "long",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

export function OrderDetailsContent({ orderNumber }: OrderDetailsContentProps) {
  const [order, setOrder] = useState<Order | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const loaded = paymentService.getOrder(orderNumber);
    setOrder(loaded);
    setIsLoading(false);
  }, [orderNumber]);

  if (isLoading) {
    return (
      <div className="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8" aria-busy="true">
        <div className="h-8 w-48 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_360px]">
          <div className="h-96 animate-pulse rounded-3xl bg-zinc-50" />
          <div className="h-80 animate-pulse rounded-3xl bg-zinc-50" />
        </div>
      </div>
    );
  }

  if (!order) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6">
        <h1 className="text-2xl font-bold text-zinc-900">Order not found</h1>
        <p className="mt-2 text-sm leading-relaxed text-zinc-500">
          We couldn&apos;t find order {orderNumber}.
        </p>
        <div className="mt-6 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
          <Link
            href="/orders"
            className="inline-flex text-sm font-semibold text-[#8b6914] hover:text-[#c9a227]"
          >
            View My Orders
          </Link>
          <Link href="/" className="inline-flex text-sm font-medium text-zinc-500 hover:text-zinc-700">
            Return to home
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
      <nav aria-label="Breadcrumb" className="mb-6">
        <ol className="flex flex-wrap items-center gap-2 text-sm text-zinc-500">
          <li>
            <Link href="/orders" className="font-medium transition hover:text-[#8b6914]">
              My Orders
            </Link>
          </li>
          <li aria-hidden>/</li>
          <li className="font-mono font-semibold text-zinc-900">{order.orderNumber}</li>
        </ol>
      </nav>

      <header className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
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
          <OrderStatusBadge status={order.status} />
          <PaymentStatusBadge status={order.paymentStatus} />
        </div>
      </header>

      <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start">
        <div className="space-y-6">
          <section
            aria-labelledby="shipping-status-heading"
            className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7"
          >
            <h2 id="shipping-status-heading" className="text-lg font-bold text-zinc-900">
              Shipping Status
            </h2>
            <div className="mt-6">
              <ShippingStatusSummary order={order} />
            </div>
          </section>

          <section
            aria-labelledby="customer-details-heading"
            className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7"
          >
            <h2 id="customer-details-heading" className="text-lg font-bold text-zinc-900">
              Customer Details
            </h2>
            <div className="mt-6">
              <OrderCustomerDetails
                customer={order.customer}
                shippingAddress={order.shippingAddress}
                orderNotes={order.orderNotes}
              />
            </div>
          </section>

          <section
            aria-labelledby="products-heading"
            className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7"
          >
            <h2 id="products-heading" className="text-lg font-bold text-zinc-900">
              Products
            </h2>
            <div className="mt-6">
              <OrderItemsList items={order.items} />
            </div>
          </section>

          <section
            aria-labelledby="timeline-heading"
            className="rounded-3xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] sm:p-7"
          >
            <h2 id="timeline-heading" className="text-lg font-bold text-zinc-900">
              Order Timeline
            </h2>
            <div className="mt-6">
              <OrderTimeline events={order.timeline} />
            </div>
          </section>
        </div>

        <aside className="lg:sticky lg:top-24 lg:self-start">
          <div className="rounded-3xl border border-zinc-200/80 bg-white p-5 shadow-[0_4px_24px_rgba(0,0,0,0.06)] sm:p-6">
            <h2 className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500">
              Order Summary
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
