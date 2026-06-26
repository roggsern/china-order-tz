"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import type { Order } from "@/lib/types/order";
import { paymentService } from "@/lib/payment/PaymentService";
import { formatPrice } from "@/lib/catalog/utils";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { OrderStatusBadge } from "./OrderStatusBadge";

function formatOrderDate(timestamp: string): string {
  return new Intl.DateTimeFormat("en-TZ", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(timestamp));
}

export function MyOrdersContent() {
  const [orders, setOrders] = useState<Order[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    setOrders(paymentService.listOrders());
    setIsLoading(false);
  }, []);

  if (isLoading) {
    return (
      <div className="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="h-8 w-48 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-8 space-y-4">
          {[1, 2, 3].map((key) => (
            <div key={key} className="h-28 animate-pulse rounded-2xl bg-zinc-50" />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
      <header>
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">Account</p>
        <h1 className="mt-1.5 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
          My Orders
        </h1>
        <p className="mt-2 text-sm leading-relaxed text-zinc-500">
          Track your orders, payment status, and shipping progress.
        </p>
      </header>

      {orders.length === 0 ? (
        <div
          className="mt-10 rounded-3xl border border-zinc-200/70 bg-white px-6 py-16 text-center shadow-[0_2px_20px_rgba(0,0,0,0.04)]"
          role="status"
        >
          <p className="text-4xl" aria-hidden>
            📦
          </p>
          <h2 className="mt-4 text-lg font-bold text-zinc-900">No orders yet</h2>
          <p className="mt-2 text-sm text-zinc-500">
            When you place an order, it will appear here.
          </p>
          <Link
            href="/products"
            className="mt-6 inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/25 transition hover:from-[#b8921f] hover:to-[#d4b83d]"
          >
            Start Shopping
          </Link>
        </div>
      ) : (
        <ul className="mt-8 space-y-4" aria-label="Order history">
          {orders.map((order) => {
            const customerName = `${order.customer.firstName} ${order.customer.lastName}`.trim();
            const itemPreview = order.items
              .slice(0, 2)
              .map((item) => item.name)
              .join(", ");
            const extraItems = order.items.length > 2 ? ` +${order.items.length - 2} more` : "";

            return (
              <li key={order.orderNumber}>
                <Link
                  href={`/orders/${order.orderNumber}`}
                  className="group block rounded-2xl border border-zinc-200/70 bg-white p-5 shadow-[0_2px_20px_rgba(0,0,0,0.04)] transition hover:border-[#c9a227]/30 hover:shadow-[0_4px_24px_rgba(201,162,39,0.08)] sm:p-6"
                  aria-label={`View order ${order.orderNumber}`}
                >
                  <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <p className="font-mono text-sm font-bold text-zinc-900 sm:text-base">
                          {order.orderNumber}
                        </p>
                        <OrderStatusBadge status={order.status} size="sm" />
                        <PaymentStatusBadge status={order.paymentStatus} size="sm" />
                      </div>

                      <p className="mt-2 text-sm text-zinc-500">
                        Placed {formatOrderDate(order.createdAt)}
                        {customerName ? ` · ${customerName}` : ""}
                      </p>

                      <p className="mt-2 line-clamp-2 text-sm leading-relaxed text-zinc-600">
                        {itemPreview}
                        {extraItems}
                      </p>
                    </div>

                    <div className="shrink-0 text-left sm:text-right">
                      <p className="text-lg font-bold text-zinc-900">
                        {formatPrice(order.totals.grandTotal)}
                      </p>
                      <p className="mt-1 text-xs text-zinc-500">
                        {order.items.length} item{order.items.length === 1 ? "" : "s"}
                      </p>
                      <span className="mt-3 inline-flex text-sm font-semibold text-[#8b6914] transition group-hover:text-[#c9a227]">
                        View details →
                      </span>
                    </div>
                  </div>
                </Link>
              </li>
            );
          })}
        </ul>
      )}
    </div>
  );
}
