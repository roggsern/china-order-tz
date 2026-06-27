"use client";

import Link from "next/link";
import { useEffect, useMemo, type ReactNode } from "react";
import { useCart } from "@/lib/cart/context";
import { clearCartIfOrderPaid, lockCartForOrder } from "@/lib/checkout/completion";
import { PAYMENT_METHOD_LABELS } from "@/lib/payment/constants";
import { PAYMENT_STATUS } from "@/lib/types/payment";
import type { Order } from "@/lib/types/order";
import { formatPrice } from "@/lib/catalog/utils";
import { formatOrderDeliveryEstimate } from "@/lib/order/delivery-estimate";
import {
  isOrderPaymentFailed,
  isOrderPaymentPending,
} from "@/lib/order/placement";
import { useOrderSnapshot } from "@/lib/order/use-order-by-id";
import { getMethodByCode } from "@/lib/shipping/engine";
import { OrderSummaryTotals } from "@/components/cart/OrderSummaryTotals";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { CopyOrderId } from "./CopyOrderId";
import { CopyOrderNumber } from "./CopyOrderNumber";
import { OrderStatusBadge } from "./OrderStatusBadge";
import { OrderSuccessItemsList } from "./OrderSuccessItemsList";
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
    <header className="text-center">
      <div
        className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-2xl text-emerald-600 shadow-inner"
        aria-hidden
      >
        ✓
      </div>
      <h1 className="mt-5 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
        Order Confirmed
      </h1>
      <p className="mt-2 text-sm leading-relaxed text-zinc-500">
        Thank you — payment was successful and we&apos;ll keep you updated on delivery.
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

  const trackOrderHref = `/track-order/${orderId}`;

  if (isLoading) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 sm:px-6" aria-busy="true">
        <div className="mx-auto h-16 w-16 animate-pulse rounded-full bg-zinc-100" />
        <div className="mx-auto mt-6 h-8 w-48 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-10 h-[28rem] animate-pulse rounded-3xl bg-zinc-50" />
      </div>
    );
  }

  if (!order) {
    return (
      <div className="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6">
        <h1 className="text-2xl font-bold text-zinc-900">Order not found</h1>
        <p className="mt-2 text-sm leading-relaxed text-zinc-500">
          We couldn&apos;t find order {orderId}.
        </p>
        <Link
          href="/"
          className="mt-6 inline-flex text-sm font-semibold text-[#8b6914] hover:text-[#c9a227]"
        >
          Return to home
        </Link>
      </div>
    );
  }

  const customerName = `${order.customer.firstName} ${order.customer.lastName}`.trim();
  const isFailed = isOrderPaymentFailed(order);

  return (
    <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
      <ConfirmationHeader order={order} />

      <article
        className="mt-10 space-y-10 rounded-3xl border border-zinc-200/80 bg-white p-6 shadow-[0_4px_24px_rgba(0,0,0,0.06)] sm:p-8"
        aria-labelledby="order-confirmation-heading"
      >
        <h2 id="order-confirmation-heading" className="sr-only">
          Order confirmation for {order.orderNumber}
        </h2>

        <section
          aria-labelledby="order-number-heading"
          className="flex flex-col gap-6 border-b border-zinc-100 pb-8 sm:flex-row sm:items-start sm:justify-between"
        >
          <div className="space-y-5">
            <div className="space-y-2">
              <SectionHeading id="order-number-heading">Order Number</SectionHeading>
              <CopyOrderNumber orderNumber={order.orderNumber} />
            </div>
            <div className="space-y-2">
              <SectionHeading id="order-id-heading">Order ID</SectionHeading>
              <CopyOrderId orderId={order.id} />
            </div>
          </div>
          <div className="sm:text-right">
            <div className="flex flex-wrap items-center justify-end gap-2">
              <OrderStatusBadge status={order.status} />
              <PaymentStatusBadge status={order.paymentStatus} size="sm" />
            </div>
          </div>
        </section>

        <section
          aria-labelledby="delivery-estimate-banner"
          className="rounded-2xl border border-[#c9a227]/20 bg-gradient-to-br from-amber-50/80 to-white px-5 py-4"
        >
          <SectionHeading id="delivery-estimate-banner">Estimated Delivery</SectionHeading>
          <p className="mt-2 text-lg font-bold text-zinc-900">{deliveryEstimate}</p>
          <p className="mt-1 text-xs text-zinc-500">
            Based on your selected shipping method{shippingMethods.length > 1 ? "s" : ""} at
            checkout.
          </p>
        </section>

        <section aria-labelledby="customer-heading" className="space-y-4">
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
          <SectionHeading id="shipping-heading">Delivery</SectionHeading>

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
            <SectionHeading id="items-heading">Items</SectionHeading>
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

        <section aria-labelledby="totals-heading" className="space-y-4 border-t border-zinc-100 pt-8">
          <SectionHeading id="totals-heading">Payment Summary</SectionHeading>
          <OrderSummaryTotals totals={order.totals} hideZeroDiscount />
          {order.paymentMethod ? (
            <p className="text-sm text-zinc-600">
              Method:{" "}
              <span className="font-semibold text-zinc-900">
                {PAYMENT_METHOD_LABELS[order.paymentMethod] ?? order.paymentMethod}
              </span>
              {order.paymentStatus === PAYMENT_STATUS.PAID && order.paymentReference ? (
                <>
                  {" "}
                  · Ref{" "}
                  <span className="font-mono text-xs">{order.paymentReference}</span>
                </>
              ) : null}
            </p>
          ) : null}
        </section>

        {isFailed ? (
          <div
            role="alert"
            className="rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-700"
          >
            Payment could not be processed. Return to checkout payment to try again with a
            different method.
          </div>
        ) : null}

        <div className="flex flex-col gap-3 border-t border-zinc-100 pt-8 sm:flex-row">
          {isFailed ? (
            <>
              <Link
                href="/checkout/payment"
                className="inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3.5 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/25 transition hover:from-[#b8921f] hover:to-[#d4b83d]"
              >
                Retry Payment
              </Link>
              <Link
                href={trackOrderHref}
                className="inline-flex flex-1 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50"
              >
                View Order Status
              </Link>
            </>
          ) : (
            <>
              <Link
                href="/"
                className="inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3.5 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/25 transition hover:from-[#b8921f] hover:to-[#d4b83d]"
              >
                Continue Shopping
              </Link>
              <Link
                href={trackOrderHref}
                className="inline-flex flex-1 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3.5 text-sm font-semibold text-zinc-700 transition hover:bg-zinc-50"
              >
                Track Order
              </Link>
            </>
          )}
        </div>
      </article>
    </div>
  );
}
