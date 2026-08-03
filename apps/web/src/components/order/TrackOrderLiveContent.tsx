"use client";

import Link from "next/link";
import { useMemo } from "react";
import { motion } from "framer-motion";
import { formatDeliveryEstimate, formatPrice } from "@/lib/catalog/utils";
import { ORDER_TRACKING_POLL_MS } from "@/lib/order/constants";
import {
  buildCustomerProgressDisplayTimeline,
  getCustomerProgressWhatHappensNext,
  isOrderCancelledForProgress,
  parseCustomerOrderProgress,
} from "@/lib/order/customer-progress";
import { splitCustomerTrackingTimeline } from "@/lib/order/customer-tracking-events";
import { resolveCustomerOrderDisplayStatus } from "@/lib/order/customer-order-display-status";
import { formatTrackingTimestamp } from "@/lib/order/tracking-format";
import { useOrderTracking } from "@/lib/order/use-order-tracking";
import { PAYMENT_METHOD_LABELS } from "@/lib/payment/constants";
import { getMethodByCode } from "@/lib/shipping/engine";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";
import { EmptyState } from "@/components/ui/EmptyState";
import { Skeleton } from "@/components/ui/Skeleton";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { CopyOrderNumber } from "./CopyOrderNumber";
import { OrderConfidenceBanner } from "./OrderConfidenceBanner";
import { OrderReceivingChoicePanel } from "./OrderReceivingChoicePanel";
import { OrderStatusBadge } from "./OrderStatusBadge";
import { OrderTrackingItemsSummary } from "./OrderTrackingItemsSummary";
import { OrderTrackingStepper } from "./OrderTrackingStepper";
import { TrackingWhatHappensNext } from "./TrackingWhatHappensNext";

interface TrackOrderLiveContentProps {
  orderId: string;
}

export function TrackOrderLiveContent({ orderId }: TrackOrderLiveContentProps) {
  const { order, tracking, isLoading, isLive, needsAuth, refresh } = useOrderTracking(orderId);

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

  const progress = useMemo(
    () =>
      parseCustomerOrderProgress(tracking?.progress ?? order?.progress ?? null),
    [tracking?.progress, order?.progress],
  );

  const displayTimeline = useMemo(() => {
    if (!order || !progress) {
      return [];
    }

    return buildCustomerProgressDisplayTimeline(progress, {
      isCancelled: isOrderCancelledForProgress(order),
      timestamps: { ORDER_CONFIRMED: order.createdAt },
    });
  }, [order, progress]);

  const operationalEvents = useMemo(
    () => splitCustomerTrackingTimeline(tracking?.timeline ?? []).operationalEvents,
    [tracking?.timeline],
  );

  const currentStep = useMemo(
    () => displayTimeline.find((step) => step.state === "current") ?? null,
    [displayTimeline],
  );

  const whatHappensNext = useMemo(
    () => (progress ? getCustomerProgressWhatHappensNext(progress) : null),
    [progress],
  );

  const paymentLabel =
    order?.paymentMethod && PAYMENT_METHOD_LABELS[order.paymentMethod]
      ? PAYMENT_METHOD_LABELS[order.paymentMethod]
      : order?.paymentMethod || "—";

  const displayStatusLabel = order
    ? resolveCustomerOrderDisplayStatus({
        status: order.status,
        progress,
        receivingChoice: order.receivingChoice,
      })
    : null;

  const currentStatusLabel =
    displayStatusLabel ??
    tracking?.current_status_label ??
    progress?.current_label ??
    tracking?.current_status ??
    "Tracking your order";

  if (isLoading) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6" aria-busy="true">
        <Skeleton className="h-8 w-48" rounded="lg" />
        <Skeleton className="mt-3 h-4 w-72 max-w-full" />
        <Skeleton className="mt-8 h-[32rem] w-full" rounded="3xl" />
      </div>
    );
  }

  if (needsAuth) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
        <AuthInvitationCard
          context="orders"
          returnUrl={`/track/${encodeURIComponent(orderId)}`}
        />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <EmptyState
          icon="🔍"
          title="Order not found"
          description={`We couldn't find an order matching “${orderId}”. Double-check your order number and try again.`}
          primaryAction={{ label: "Track another order", href: "/track" }}
          secondaryAction={{ label: "View My Orders", href: "/orders" }}
        />
      </div>
    );
  }

  const detailsHref = `/orders/${encodeURIComponent(order.orderNumber)}`;
  const shipment = tracking?.shipment_summary;

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
          <h1 className="mt-1.5 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
            Order Progress
          </h1>
          <p className="mt-2 text-sm text-zinc-500">
            {currentStep?.description ?? currentStatusLabel}
          </p>
        </div>
        <div className="flex flex-col items-start gap-2 sm:items-end">
          <div className="flex flex-wrap gap-2">
            <PaymentStatusBadge status={order.paymentStatus} size="sm" />
            <OrderStatusBadge
              status={order.status}
              label={displayStatusLabel ?? undefined}
              size="sm"
            />
          </div>
          <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ${
              isLive
                ? "bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20"
                : "bg-zinc-100 text-zinc-500 ring-1 ring-zinc-200"
            }`}
          >
            <span
              className={`h-1.5 w-1.5 rounded-full ${isLive ? "animate-pulse bg-emerald-500" : "bg-zinc-400"}`}
              aria-hidden
            />
            {isLive ? "Live" : "Syncing"}
          </span>
        </div>
      </motion.header>

      <OrderConfidenceBanner className="mt-6" />

      <motion.article
        initial={{ opacity: 0, y: 14 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.06 }}
        className="mt-8 space-y-8 rounded-3xl border border-zinc-200/80 bg-white p-6 shadow-[0_4px_24px_rgba(0,0,0,0.06)] sm:p-8"
      >
        <CopyOrderNumber orderNumber={order.orderNumber} />

        <section aria-labelledby="tracking-progress-heading">
          <h2
            id="tracking-progress-heading"
            className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500"
          >
            Tracking Timeline
          </h2>
          <div className="mt-6">
            <OrderTrackingStepper timeline={displayTimeline} tone="light" />
          </div>
        </section>

        <TrackingWhatHappensNext guidance={whatHappensNext} />

        <OrderReceivingChoicePanel
          orderNumber={order.orderNumber}
          receivingChoice={order.receivingChoice}
          onUpdated={refresh}
        />

        {shipment ? (
          <section
            aria-labelledby="shipment-info-heading"
            className="space-y-4 border-t border-zinc-100 pt-8"
          >
            <h2
              id="shipment-info-heading"
              className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500"
            >
              Shipment
            </h2>
            <dl className="grid gap-4 rounded-2xl border border-zinc-100 bg-zinc-50/80 p-4 sm:grid-cols-2 sm:p-5">
              <div>
                <dt className="text-xs font-medium text-zinc-500">Shipment number</dt>
                <dd className="mt-1.5 font-mono text-sm font-semibold text-zinc-900">
                  {shipment.shipment_number}
                </dd>
              </div>
              {shipment.carrier_name ? (
                <div>
                  <dt className="text-xs font-medium text-zinc-500">Carrier</dt>
                  <dd className="mt-1.5 text-sm font-semibold text-zinc-900">
                    {shipment.carrier_name}
                  </dd>
                </div>
              ) : null}
              {shipment.tracking_reference ? (
                <div>
                  <dt className="text-xs font-medium text-zinc-500">Tracking reference</dt>
                  <dd className="mt-1.5 font-mono text-sm font-semibold text-zinc-900">
                    {shipment.tracking_reference}
                  </dd>
                </div>
              ) : null}
              {shipment.status_label || shipment.status ? (
                <div>
                  <dt className="text-xs font-medium text-zinc-500">Shipment status</dt>
                  <dd className="mt-1.5 text-sm font-semibold text-zinc-900">
                    {shipment.status_label ?? shipment.status}
                  </dd>
                </div>
              ) : null}
            </dl>
          </section>
        ) : null}

        {operationalEvents.length > 0 ? (
          <section
            aria-labelledby="tracking-events-heading"
            className="space-y-4 border-t border-zinc-100 pt-8"
          >
            <h2
              id="tracking-events-heading"
              className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500"
            >
              Tracking Updates
            </h2>
            <ol className="space-y-3">
              {operationalEvents.map((event) => (
                <li
                  key={event.id}
                  className="rounded-2xl border border-zinc-100 bg-zinc-50/80 px-4 py-3"
                >
                  <p className="text-sm font-semibold text-zinc-900">
                    {event.event_type_label}
                  </p>
                  {event.description ? (
                    <p className="mt-1 text-sm text-zinc-600">{event.description}</p>
                  ) : null}
                  <div className="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500">
                    {event.location ? <span>{event.location}</span> : null}
                    {event.event_at ? (
                      <time dateTime={event.event_at}>
                        {formatTrackingTimestamp(event.event_at)}
                      </time>
                    ) : null}
                  </div>
                </li>
              ))}
            </ol>
          </section>
        ) : null}

        <section
          aria-labelledby="delivery-info-heading"
          className="space-y-4 border-t border-zinc-100 pt-8"
        >
          <h2
            id="delivery-info-heading"
            className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500"
          >
            Shipping & Payment
          </h2>

          <dl className="grid gap-4 rounded-2xl border border-zinc-100 bg-zinc-50/80 p-4 sm:grid-cols-2 sm:p-5">
            <div>
              <dt className="text-xs font-medium text-zinc-500">Current status</dt>
              <dd className="mt-1.5">
                <span className="inline-flex rounded-full bg-[#c9a227]/15 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-[#8b6914]">
                  {currentStatusLabel}
                </span>
              </dd>
            </div>

            <div>
              <dt className="text-xs font-medium text-zinc-500">Shipping method</dt>
              <dd className="mt-1.5 flex flex-wrap gap-2">
                {shippingMethods.length > 0 ? (
                  shippingMethods.map((code) => {
                    const method = getMethodByCode(code);
                    return (
                      <span
                        key={code}
                        className="inline-flex items-center gap-1.5 text-sm font-semibold text-zinc-900"
                      >
                        <span aria-hidden>{method?.icon}</span>
                        {method?.name ?? code}
                      </span>
                    );
                  })
                ) : (
                  <span className="text-sm text-zinc-500">—</span>
                )}
              </dd>
            </div>

            <div>
              <dt className="text-xs font-medium text-zinc-500">Estimated delivery</dt>
              <dd className="mt-1.5 text-sm font-semibold text-zinc-900">
                {deliveryEstimates.length > 0
                  ? deliveryEstimates.map((estimate) => formatDeliveryEstimate(estimate)).join(", ")
                  : "—"}
              </dd>
            </div>

            <div>
              <dt className="text-xs font-medium text-zinc-500">Payment method</dt>
              <dd className="mt-1.5 text-sm font-semibold text-zinc-900">{paymentLabel}</dd>
            </div>

            {order.paymentReference ? (
              <div>
                <dt className="text-xs font-medium text-zinc-500">Payment reference</dt>
                <dd className="mt-1.5 font-mono text-sm font-semibold text-zinc-900">
                  {order.paymentReference}
                </dd>
              </div>
            ) : null}

            <div>
              <dt className="text-xs font-medium text-zinc-500">Order total</dt>
              <dd className="mt-1.5 text-sm font-bold text-[#8b6914]">
                {formatPrice(order.totals.grandTotal)}
              </dd>
            </div>

            <div>
              <dt className="text-xs font-medium text-zinc-500">Shipping cost</dt>
              <dd className="mt-1.5 text-sm font-semibold text-zinc-900">
                {formatPrice(order.totals.shippingTotal)}
              </dd>
            </div>

            <div className="sm:col-span-2">
              <dt className="text-xs font-medium text-zinc-500">Deliver to</dt>
              <dd className="mt-1.5 text-sm font-semibold text-zinc-900">
                {order.shippingAddress.addressLine1}, {order.shippingAddress.city},{" "}
                {order.shippingAddress.region}
              </dd>
            </div>
          </dl>
        </section>

        <section
          aria-labelledby="items-summary-heading"
          className="space-y-4 border-t border-zinc-100 pt-8"
        >
          <div className="flex items-center justify-between gap-3">
            <h2
              id="items-summary-heading"
              className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500"
            >
              Order Summary
            </h2>
            <span className="text-xs font-medium text-zinc-500">
              {order.items.length} item{order.items.length === 1 ? "" : "s"}
            </span>
          </div>
          <OrderTrackingItemsSummary items={order.items} tone="light" />
        </section>

        <div className="flex flex-col gap-3 border-t border-zinc-100 pt-8 sm:flex-row">
          <Link
            href={detailsHref}
            className="inline-flex flex-1 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-800 transition hover:border-[#c9a227]/35 hover:bg-zinc-50"
          >
            View Order Details
          </Link>
          <Link
            href="/orders"
            className="inline-flex flex-1 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-800 transition hover:border-[#c9a227]/35 hover:bg-zinc-50"
          >
            View Orders
          </Link>
          <Link
            href="/products"
            className="inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/25 transition hover:from-[#b8921f] hover:to-[#d4b83d]"
          >
            Continue Shopping
          </Link>
        </div>
      </motion.article>

      <p className="mt-4 text-center text-xs text-zinc-500">
        Status updates automatically every {ORDER_TRACKING_POLL_MS / 1000} seconds.
      </p>
    </div>
  );
}
