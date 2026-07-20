"use client";

import Link from "next/link";
import { useEffect, useMemo, type ReactNode } from "react";
import { useCart } from "@/lib/cart/context";
import { clearCartIfOrderPaid, lockCartForOrder } from "@/lib/checkout/completion";
import { PAYMENT_METHOD_LABELS, BANK_TRANSFER_INSTRUCTIONS } from "@/lib/payment/constants";
import { PAYMENT_METHOD_CODES, PAYMENT_STATUS } from "@/lib/types/payment";
import type { Order } from "@/lib/types/order";
import { formatPrice } from "@/lib/catalog/utils";
import { formatOrderDeliveryEstimate } from "@/lib/order/delivery-estimate";
import {
  isOrderPaymentFailed,
  isOrderPaymentPending,
} from "@/lib/order/placement";
import { useOrderSnapshot } from "@/lib/order/use-order-by-id";
import { buildCustomerTrackingDisplayTimeline } from "@/lib/order/tracking-display";
import { getMethodByCode } from "@/lib/shipping/engine";
import { OrderSummaryTotals } from "@/components/cart/OrderSummaryTotals";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { EmptyState } from "@/components/ui/EmptyState";
import { Skeleton } from "@/components/ui/Skeleton";
import { CopyOrderNumber } from "./CopyOrderNumber";
import { OrderConfidenceBanner } from "./OrderConfidenceBanner";
import { OrderStatusBadge } from "./OrderStatusBadge";
import { OrderSuccessItemsList } from "./OrderSuccessItemsList";
import { OrderTrackingStepper } from "./OrderTrackingStepper";
import { TrackingWhatHappensNext } from "./TrackingWhatHappensNext";
import { ShippingBreakdownList } from "@/components/shipping/ShippingQuantityBreakdown";

interface OrderSuccessContentProps {
  orderId: string;
}

function SectionHeading({ id, children }: { id: string; children: ReactNode }) {
  return (
    <h2 id={id} className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500">
      {children}
    </h2>
  );
}

function ConfirmationHeader({ order }: { order: Order }) {
  if (isOrderPaymentFailed(order)) {
    return (
      <header className="text-center">
        <div
          className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 text-2xl text-red-600 shadow-inner"
          aria-hidden
        >
          ✕
        </div>
        <h1 className="mt-5 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
          Payment Failed
        </h1>
        <p className="mt-2 text-sm leading-relaxed text-zinc-500">
          Your order was saved but payment did not complete. You can retry from the payment page.
        </p>
      </header>
    );
  }

  if (isOrderPaymentPending(order)) {
    return (
      <header className="text-center">
        <div
          className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-2xl text-amber-600 shadow-inner"
          aria-hidden
        >
          ⏳
        </div>
        <h1 className="mt-5 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
          Order Received
        </h1>
        <p className="mt-2 text-sm leading-relaxed text-zinc-500">
          Thank you — we&apos;ve received your order. Complete payment to confirm, or pay on
          delivery if you chose COD.
        </p>
      </header>
    );
  }

  return (
    <header className="relative overflow-hidden rounded-3xl border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-[#c9a227]/10 px-6 py-10 text-center shadow-[0_8px_40px_rgba(16,185,129,0.08)] sm:px-10">
      <div
        className="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-emerald-200/30 blur-3xl"
        aria-hidden
      />
      <div
        className="relative mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-3xl text-emerald-600 shadow-inner"
        aria-hidden
      >
        🎉
      </div>
      <h1 className="relative mt-5 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
        Order Confirmed
      </h1>
      <p className="relative mx-auto mt-3 max-w-md text-sm leading-relaxed text-zinc-500 sm:text-base">
        Thank you for your order. Payment was successful — we&apos;ll keep you updated every step of
        the way.
      </p>
    </header>
  );
}

export function OrderSuccessContent({ orderId }: OrderSuccessContentProps) {
  const { clearPurchasedItems } = useCart();
  const { order, isLoading } = useOrderSnapshot(orderId);

  useEffect(() => {
    if (!order) {
      return;
    }

    if (isOrderPaymentFailed(order)) {
      return;
    }

    lockCartForOrder(order.id, clearPurchasedItems);
    clearCartIfOrderPaid(orderId, clearPurchasedItems);
  }, [clearPurchasedItems, order, orderId]);

  const shippingMethods = useMemo(() => {
    if (!order) return [];
    return [...new Set(order.items.map((item) => item.shippingMethod))];
  }, [order]);

  const deliveryEstimate = useMemo(() => {
    if (!order) return "—";
    return formatOrderDeliveryEstimate(order);
  }, [order]);

  const displayTimeline = useMemo(() => {
    if (!order) return [];
    return buildCustomerTrackingDisplayTimeline(order, null);
  }, [order]);

  const trackOrderHref = `/track/${orderId}`;

  if (isLoading) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6" aria-busy="true">
        <Skeleton className="mx-auto h-40 w-full" rounded="3xl" />
        <Skeleton className="mt-8 h-[28rem] w-full" rounded="3xl" />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <EmptyState
          icon="📦"
          title="Order not found"
          description={`We couldn't find confirmation for ${orderId}.`}
          primaryAction={{ label: "View My Orders", href: "/orders" }}
          secondaryAction={{ label: "Return home", href: "/" }}
        />
      </div>
    );
  }

  const customerName = `${order.customer.firstName} ${order.customer.lastName}`.trim();
  const isFailed = isOrderPaymentFailed(order);
  const isPending = isOrderPaymentPending(order);
  const paymentLabel =
    (order.paymentMethod && PAYMENT_METHOD_LABELS[order.paymentMethod]) ||
    order.paymentMethod ||
    "—";

  return (
    <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
      <ConfirmationHeader order={order} />

      {!isFailed ? (
        <OrderConfidenceBanner className="mt-8 animate-fade-in" />
      ) : null}

      <article
        className="mt-8 space-y-8 rounded-3xl border border-zinc-200/80 bg-white p-6 shadow-[0_4px_24px_rgba(0,0,0,0.06)] animate-fade-in sm:p-8"
        aria-labelledby="order-confirmation-heading"
      >
        <h2 id="order-confirmation-heading" className="sr-only">
          Order confirmation for {order.orderNumber}
        </h2>

        <section
          aria-labelledby="order-meta-heading"
          className="grid gap-5 border-b border-zinc-100 pb-8 sm:grid-cols-2"
        >
          <div className="space-y-2 sm:col-span-2">
            <SectionHeading id="order-meta-heading">Order Number</SectionHeading>
            <CopyOrderNumber orderNumber={order.orderNumber} />
          </div>

          <div className="space-y-2">
            <SectionHeading id="payment-ref-heading">Payment Reference</SectionHeading>
            <p className="font-mono text-sm font-semibold text-zinc-900">
              {order.paymentStatus === PAYMENT_STATUS.PAID && order.paymentReference
                ? order.paymentReference
                : isPending
                  ? "Pending confirmation"
                  : "—"}
            </p>
          </div>

          <div className="space-y-2">
            <SectionHeading id="delivery-estimate-heading">Estimated Delivery</SectionHeading>
            <p className="text-sm font-bold text-zinc-900">{deliveryEstimate}</p>
            <p className="text-xs text-zinc-500">
              Based on your selected shipping method{shippingMethods.length > 1 ? "s" : ""}.
            </p>
          </div>

          <div className="flex flex-wrap gap-2 sm:col-span-2">
            <PaymentStatusBadge status={order.paymentStatus} />
            <OrderStatusBadge status={order.status} />
          </div>
        </section>

        {!isFailed && displayTimeline.length > 0 ? (
          <section aria-labelledby="progress-heading" className="space-y-5">
            <SectionHeading id="progress-heading">Order Progress</SectionHeading>
            <OrderTrackingStepper timeline={displayTimeline} tone="light" />
            <TrackingWhatHappensNext timeline={displayTimeline} />
          </section>
        ) : null}

        <section aria-labelledby="customer-heading" className="space-y-4 border-t border-zinc-100 pt-8">
          <SectionHeading id="customer-heading">Customer</SectionHeading>
          <ul className="space-y-2 text-sm">
            <li className="font-semibold text-zinc-900">{customerName || "—"}</li>
            {order.customer.phone ? (
              <li className="text-zinc-600">{order.customer.phone}</li>
            ) : null}
            {order.customer.email ? (
              <li className="text-zinc-600">{order.customer.email}</li>
            ) : null}
          </ul>
        </section>

        <section
          aria-labelledby="shipping-heading"
          className="space-y-5 border-t border-zinc-100 pt-8"
        >
          <SectionHeading id="shipping-heading">Shipping</SectionHeading>

          <dl className="grid gap-5 sm:grid-cols-2">
            <div className="space-y-2">
              <dt className="text-xs font-medium text-zinc-500">Shipping method</dt>
              <dd className="flex flex-wrap gap-2">
                {shippingMethods.map((code) => {
                  const method = getMethodByCode(code);
                  return (
                    <span
                      key={code}
                      className="inline-flex items-center gap-1.5 rounded-lg bg-zinc-100 px-3 py-1.5 text-sm font-medium text-zinc-800"
                    >
                      <span aria-hidden>{method?.icon}</span>
                      {method?.name ?? code}
                    </span>
                  );
                })}
              </dd>
            </div>

            <div className="space-y-2">
              <dt className="text-xs font-medium text-zinc-500">Shipping cost</dt>
              <dd className="text-sm font-semibold text-zinc-900">
                {formatPrice(order.totals.shippingTotal)}
              </dd>
            </div>

            <div className="space-y-2 sm:col-span-2">
              <dt className="text-xs font-medium text-zinc-500">Deliver to</dt>
              <dd className="text-sm font-semibold text-zinc-900">
                {order.shippingAddress.addressLine1}
                {order.shippingAddress.addressLine2
                  ? `, ${order.shippingAddress.addressLine2}`
                  : ""}
                <br />
                {order.shippingAddress.city}, {order.shippingAddress.region}
              </dd>
            </div>
          </dl>
        </section>

        <section aria-labelledby="items-heading" className="space-y-5 border-t border-zinc-100 pt-8">
          <div className="flex items-center justify-between gap-3">
            <SectionHeading id="items-heading">Order Summary</SectionHeading>
            <span className="text-xs font-medium text-zinc-500">
              {order.items.length} item{order.items.length === 1 ? "" : "s"}
            </span>
          </div>
          <OrderSuccessItemsList items={order.items} />
          {order.itemShippingBreakdown?.length ? (
            <div className="rounded-2xl border border-zinc-100 bg-zinc-50/60 p-4">
              <p className="text-xs font-semibold uppercase tracking-[0.12em] text-zinc-500">
                Shipping breakdown
              </p>
              <ShippingBreakdownList rows={order.itemShippingBreakdown} className="mt-2" />
            </div>
          ) : null}
        </section>

        <section aria-labelledby="payment-heading" className="space-y-4 border-t border-zinc-100 pt-8">
          <SectionHeading id="payment-heading">Payment & Totals</SectionHeading>
          <p className="text-sm text-zinc-600">
            Method:{" "}
            <span className="font-semibold text-zinc-900">{paymentLabel}</span>
          </p>
          <OrderSummaryTotals totals={order.totals} hideZeroDiscount />
        </section>

        {order.paymentMethod === PAYMENT_METHOD_CODES.BANK_TRANSFER ? (
          <section
            aria-labelledby="bank-transfer-heading"
            className="rounded-2xl border border-amber-200 bg-amber-50/70 px-5 py-5"
          >
            <SectionHeading id="bank-transfer-heading">Bank Transfer Instructions</SectionHeading>
            <p className="mt-2 text-sm text-zinc-700">
              Complete your transfer using the details below. Your order stays pending until we
              confirm payment. You can finish this later from your orders page.
            </p>
            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
              <div>
                <dt className="text-xs font-medium text-zinc-500">Bank</dt>
                <dd className="font-semibold text-zinc-900">{BANK_TRANSFER_INSTRUCTIONS.bankName}</dd>
              </div>
              <div>
                <dt className="text-xs font-medium text-zinc-500">Account name</dt>
                <dd className="font-semibold text-zinc-900">
                  {BANK_TRANSFER_INSTRUCTIONS.accountName}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-medium text-zinc-500">Account number</dt>
                <dd className="font-mono font-semibold text-zinc-900">
                  {BANK_TRANSFER_INSTRUCTIONS.accountNumber}
                </dd>
              </div>
              <div>
                <dt className="text-xs font-medium text-zinc-500">Branch</dt>
                <dd className="font-semibold text-zinc-900">{BANK_TRANSFER_INSTRUCTIONS.branch}</dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-xs font-medium text-zinc-500">Amount</dt>
                <dd className="font-semibold text-zinc-900">{formatPrice(order.grandTotal)}</dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-xs font-medium text-zinc-500">Reference</dt>
                <dd className="font-mono font-semibold text-zinc-900">{order.orderNumber}</dd>
                <p className="mt-1 text-xs text-zinc-500">
                  {BANK_TRANSFER_INSTRUCTIONS.referenceHint}
                </p>
              </div>
            </dl>
          </section>
        ) : null}

        {isFailed ? (
          <div
            role="alert"
            className="rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700"
          >
            Payment could not be processed. Return to checkout payment to try again with a
            different method.
          </div>
        ) : null}

        <div className="flex flex-col gap-3 border-t border-zinc-100 pt-8 sm:flex-row sm:flex-wrap">
          {isFailed ? (
            <>
              <Link
                href="/checkout/payment"
                className="inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3.5 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/25 transition hover:from-[#b8921f] hover:to-[#d4b83d] sm:min-w-[160px]"
              >
                Retry Payment
              </Link>
              <Link
                href={trackOrderHref}
                className="inline-flex flex-1 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50 sm:min-w-[140px]"
              >
                View Order Status
              </Link>
            </>
          ) : (
            <>
              <Link
                href={trackOrderHref}
                className="inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3.5 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/25 transition hover:from-[#b8921f] hover:to-[#d4b83d] sm:min-w-[140px]"
              >
                Track Order
              </Link>
              <Link
                href="/products"
                className="inline-flex flex-1 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3.5 text-sm font-semibold text-zinc-800 transition hover:border-[#c9a227]/35 hover:bg-zinc-50 sm:min-w-[140px]"
              >
                Continue Shopping
              </Link>
              <Link
                href="/orders"
                className="inline-flex flex-1 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3.5 text-sm font-semibold text-zinc-800 transition hover:border-[#c9a227]/35 hover:bg-zinc-50 sm:min-w-[140px]"
              >
                View Orders
              </Link>
              <button
                type="button"
                disabled
                title="Invoice download will be available soon"
                className="inline-flex flex-1 cursor-not-allowed items-center justify-center rounded-xl border border-zinc-100 bg-zinc-50 px-5 py-3.5 text-sm font-semibold text-zinc-400 sm:min-w-[140px]"
              >
                Invoice
              </button>
            </>
          )}
        </div>
      </article>
    </div>
  );
}
