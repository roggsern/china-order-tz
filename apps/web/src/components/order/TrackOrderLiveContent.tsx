"use client";

import Link from "next/link";
import { useMemo } from "react";
import { motion } from "framer-motion";
import { formatDeliveryEstimate, formatPrice } from "@/lib/catalog/utils";
import { ORDER_TRACKING_POLL_MS } from "@/lib/order/constants";
import { getTrackingHeadlineFromTimeline } from "@/lib/order/tracking-status";
import { useOrderTracking } from "@/lib/order/use-order-tracking";
import { DELIVERY_STATUS_LABELS } from "@/lib/delivery/delivery-labels";
import { getMethodByCode } from "@/lib/shipping/engine";
import { CopyOrderNumber } from "./CopyOrderNumber";
import { OrderStatusBadge } from "./OrderStatusBadge";
import { OrderTrackingItemsSummary } from "./OrderTrackingItemsSummary";
import { OrderTrackingStepper } from "./OrderTrackingStepper";

interface TrackOrderLiveContentProps {
  orderId: string;
}

export function TrackOrderLiveContent({ orderId }: TrackOrderLiveContentProps) {
  const { order, delivery, timeline, isLoading, isLive } = useOrderTracking(orderId);

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

  if (isLoading) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 sm:px-6" aria-busy="true">
        <div className="h-10 w-64 animate-pulse rounded-lg bg-zinc-800" />
        <div className="mt-8 h-96 animate-pulse rounded-3xl bg-zinc-900" />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6">
        <h1 className="text-2xl font-bold text-zinc-100">Order not found</h1>
        <p className="mt-2 text-sm text-zinc-400">
          We couldn&apos;t find an order matching &ldquo;{orderId}&rdquo;.
        </p>
        <Link
          href="/track"
          className="mt-6 inline-flex rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/25"
        >
          Try another order ID
        </Link>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
      <motion.header
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
      >
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            Track Order
          </p>
          <h1 className="mt-1.5 text-2xl font-bold tracking-tight text-zinc-50 sm:text-3xl">
            Order Progress
          </h1>
          <p className="mt-2 text-sm text-zinc-400">{getTrackingHeadlineFromTimeline(timeline)}</p>
        </div>
        <div className="flex flex-col items-start gap-2 sm:items-end">
          <OrderStatusBadge status={order.status} />
          <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ${
              isLive
                ? "bg-emerald-500/15 text-emerald-400"
                : "bg-zinc-800 text-zinc-500"
            }`}
          >
            <span
              className={`h-1.5 w-1.5 rounded-full ${isLive ? "bg-emerald-400 animate-pulse" : "bg-zinc-600"}`}
              aria-hidden
            />
            {isLive ? "Live" : "Syncing"}
          </span>
        </div>
      </motion.header>

      <motion.article
        initial={{ opacity: 0, y: 14 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.06 }}
        className="mt-8 space-y-10 rounded-3xl border border-zinc-800 bg-zinc-950 p-6 shadow-[0_8px_40px_rgba(0,0,0,0.45)] sm:p-8"
      >
        <CopyOrderNumber orderNumber={order.orderNumber} />

        <section aria-labelledby="tracking-progress-heading">
          <h2
            id="tracking-progress-heading"
            className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500"
          >
            Tracking Progress
          </h2>
          <div className="mt-6">
            <OrderTrackingStepper timeline={timeline} />
          </div>
        </section>

        <section
          aria-labelledby="delivery-info-heading"
          className="space-y-4 border-t border-zinc-800 pt-8"
        >
          <h2
            id="delivery-info-heading"
            className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500"
          >
            Delivery Info
          </h2>

          <dl className="grid gap-4 rounded-2xl bg-zinc-900/80 p-4 sm:grid-cols-2 sm:p-5">
            {delivery ? (
              <>
                <div>
                  <dt className="text-xs font-medium text-zinc-500">Logistics status</dt>
                  <dd className="mt-1.5">
                    <span className="inline-flex rounded-full bg-[#c9a227]/15 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-[#e8c547]">
                      {DELIVERY_STATUS_LABELS[delivery.status]}
                    </span>
                  </dd>
                </div>
                {delivery.assignedDriver ? (
                  <div>
                    <dt className="text-xs font-medium text-zinc-500">Delivery driver</dt>
                    <dd className="mt-1.5 text-sm font-semibold text-zinc-100">
                      {delivery.assignedDriver}
                    </dd>
                  </div>
                ) : null}
              </>
            ) : null}

            <div>
              <dt className="text-xs font-medium text-zinc-500">Shipping method</dt>
              <dd className="mt-1.5 flex flex-wrap gap-2">
                {shippingMethods.map((code) => {
                  const method = getMethodByCode(code);
                  return (
                    <span
                      key={code}
                      className="inline-flex items-center gap-1.5 text-sm font-semibold text-zinc-100"
                    >
                      <span aria-hidden>{method?.icon}</span>
                      {method?.name ?? code}
                    </span>
                  );
                })}
              </dd>
            </div>

            <div>
              <dt className="text-xs font-medium text-zinc-500">Estimated delivery</dt>
              <dd className="mt-1.5 text-sm font-semibold text-zinc-100">
                {deliveryEstimates.length > 0
                  ? deliveryEstimates.map((estimate) => formatDeliveryEstimate(estimate)).join(", ")
                  : "—"}
              </dd>
            </div>

            <div>
              <dt className="text-xs font-medium text-zinc-500">Order total</dt>
              <dd className="mt-1.5 text-sm font-bold text-[#e8c547]">
                {formatPrice(order.totals.grandTotal)}
              </dd>
            </div>

            <div>
              <dt className="text-xs font-medium text-zinc-500">Shipping cost</dt>
              <dd className="mt-1.5 text-sm font-semibold text-zinc-100">
                {formatPrice(order.totals.shippingTotal)}
              </dd>
            </div>

            <div className="sm:col-span-2">
              <dt className="text-xs font-medium text-zinc-500">Deliver to</dt>
              <dd className="mt-1.5 text-sm font-semibold text-zinc-100">
                {order.shippingAddress.addressLine1}, {order.shippingAddress.city},{" "}
                {order.shippingAddress.region}
              </dd>
            </div>
          </dl>
        </section>

        <section
          aria-labelledby="items-summary-heading"
          className="space-y-4 border-t border-zinc-800 pt-8"
        >
          <div className="flex items-center justify-between gap-3">
            <h2
              id="items-summary-heading"
              className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500"
            >
              Items Ordered
            </h2>
            <span className="text-xs font-medium text-zinc-500">
              {order.items.length} item{order.items.length === 1 ? "" : "s"}
            </span>
          </div>
          <OrderTrackingItemsSummary items={order.items} />
        </section>

        <div className="flex flex-col gap-3 border-t border-zinc-800 pt-8 sm:flex-row">
          <Link
            href={`/order-success/${order.id}`}
            className="inline-flex flex-1 items-center justify-center rounded-xl border border-zinc-700 bg-zinc-900 px-5 py-3 text-sm font-semibold text-zinc-200 transition hover:bg-zinc-800"
          >
            View Confirmation
          </Link>
          <Link
            href="/track"
            className="inline-flex flex-1 items-center justify-center rounded-xl border border-zinc-700 bg-zinc-900 px-5 py-3 text-sm font-semibold text-zinc-200 transition hover:bg-zinc-800"
          >
            Track Another Order
          </Link>
          <Link
            href="/"
            className="inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/25 transition hover:from-[#b8921f] hover:to-[#d4b83d]"
          >
            Continue Shopping
          </Link>
        </div>
      </motion.article>

      <p className="mt-4 text-center text-xs text-zinc-500">
        Status updates automatically{isLive ? " in real time" : ""} every{" "}
        {ORDER_TRACKING_POLL_MS / 1000} seconds.
      </p>
    </div>
  );
}
