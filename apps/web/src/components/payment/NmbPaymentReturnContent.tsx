"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import {
  clearNmbCheckoutContext,
  readNmbCheckoutContext,
} from "@/lib/nmb/checkout-context";

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

  return "pending";
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
    setStatus(resolveReturnStatus(resultIndicator));
  }, [resultIndicator]);

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
