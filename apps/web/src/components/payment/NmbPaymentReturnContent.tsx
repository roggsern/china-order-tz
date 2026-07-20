"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import {
  clearNmbCheckoutContext,
  readNmbCheckoutContext,
} from "@/lib/nmb/checkout-context";
import { updateOrderById } from "@/lib/payment/order-storage";
import { ORDER_STATUS } from "@/lib/types/order";
import { PAYMENT_METHOD_CODES, PAYMENT_STATUS } from "@/lib/types/payment";
import { syncTimelineWithOrder } from "@/lib/payment/timeline";

type ReturnStatus = "success" | "pending" | "failed";

function resolveReturnStatus(resultIndicator: string | null): ReturnStatus {
  const context = readNmbCheckoutContext();

  if (!resultIndicator) {
    return "pending";
  }

  if (context?.successIndicator) {
    return context.successIndicator === resultIndicator ? "success" : "failed";
  }

  if (context?.resultIndicator) {
    return context.resultIndicator === resultIndicator ? "success" : "failed";
  }

  // Hosted checkout returned a resultIndicator — treat as submitted success pending webhook.
  return "success";
}

function markLocalOrderPaid(localOrderId: string | null): void {
  if (!localOrderId) {
    return;
  }

  updateOrderById(localOrderId, (existing) => {
    const paid = {
      ...existing,
      paymentMethod: existing.paymentMethod ?? PAYMENT_METHOD_CODES.NMB,
      paymentStatus: PAYMENT_STATUS.PAID,
      status: ORDER_STATUS.CONFIRMED,
      updatedAt: new Date().toISOString(),
    };

    paid.timeline = syncTimelineWithOrder(paid);
    return paid;
  });
}

export function NmbPaymentReturnContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [status, setStatus] = useState<ReturnStatus>("pending");

  const resultIndicator = searchParams.get("resultIndicator");
  const orderId = searchParams.get("orderId") ?? readNmbCheckoutContext()?.orderId ?? null;
  const localOrderId =
    searchParams.get("localOrderId") ?? readNmbCheckoutContext()?.localOrderId ?? null;

  const successHref = useMemo(() => {
    if (localOrderId) {
      return `/order-success/${localOrderId}`;
    }

    if (orderId) {
      return `/orders/${orderId}`;
    }

    return "/orders";
  }, [localOrderId, orderId]);

  useEffect(() => {
    const next = resolveReturnStatus(resultIndicator);
    setStatus(next);

    if (next === "success") {
      markLocalOrderPaid(localOrderId);
    }
  }, [resultIndicator, localOrderId]);

  useEffect(() => {
    if (status !== "success") {
      return;
    }

    const timeout = window.setTimeout(() => {
      clearNmbCheckoutContext();
      router.replace(successHref);
    }, 1200);

    return () => window.clearTimeout(timeout);
  }, [router, status, successHref]);

  return (
    <div className="mx-auto max-w-lg rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
      {status === "success" ? (
        <>
          <h1 className="text-xl font-semibold text-zinc-900">Payment received</h1>
          <p className="mt-3 text-sm leading-6 text-zinc-600">
            Your NMB payment was submitted successfully. Redirecting you now…
          </p>
        </>
      ) : status === "failed" ? (
        <>
          <h1 className="text-xl font-semibold text-zinc-900">Payment failed</h1>
          <p className="mt-3 text-sm leading-6 text-zinc-600">
            Your NMB payment could not be confirmed. You can return to checkout and try again.
          </p>
          <Link
            href="/checkout/payment"
            className="mt-6 inline-flex rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white"
          >
            Retry payment
          </Link>
        </>
      ) : (
        <>
          <h1 className="text-xl font-semibold text-zinc-900">Payment status pending</h1>
          <p className="mt-3 text-sm leading-6 text-zinc-600">
            We could not confirm the final payment result yet. You can review your order status
            shortly.
          </p>
          <Link
            href={successHref}
            className="mt-6 inline-flex rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white"
          >
            View order
          </Link>
        </>
      )}
    </div>
  );
}
