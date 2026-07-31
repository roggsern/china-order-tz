"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  PaymentOrchestratorApiError,
  refreshPaymentTransaction,
  resolvePaymentReturnTransaction,
  type PaymentTransactionPayload,
} from "@/lib/api/customer-payment-orchestrator";
import {
  clearNmbCheckoutContext,
  consumeNmbPendingPaymentId,
  readNmbCheckoutContext,
} from "@/lib/nmb/checkout-context";
import {
  buildSuccessHref,
  getReturnPhaseCopy,
  looksLikeMerchantReference,
  resolveIndicatorGate,
  resolveInitialReturnPhase,
  resolvePhaseAfterRefreshError,
  resolvePhaseAfterTransaction,
  shouldAttemptReturnResolution,
  shouldReconcileReturn,
  type ReturnPhase,
} from "@/lib/nmb/payment-return";

const POLL_INTERVAL_MS = 4000;

export function NmbPaymentReturnContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);
  const reconciliationStartedRef = useRef(false);
  const resolutionStartedRef = useRef(false);

  const resultIndicator = searchParams.get("resultIndicator");
  const orderId = searchParams.get("orderId") ?? readNmbCheckoutContext()?.orderId ?? null;
  const localOrderId =
    searchParams.get("localOrderId") ?? readNmbCheckoutContext()?.localOrderId ?? null;
  const merchantReferenceParam = searchParams.get("merchantReference");
  const [resolvedTransactionId, setResolvedTransactionId] = useState<string | null>(() => {
    return (
      searchParams.get("paymentTransactionId") ??
      readNmbCheckoutContext()?.paymentTransactionId ??
      readNmbCheckoutContext()?.paymentId ??
      consumeNmbPendingPaymentId() ??
      null
    );
  });
  const paymentTransactionId = resolvedTransactionId;
  const successIndicator = readNmbCheckoutContext()?.successIndicator ?? null;
  const indicatorGate = useMemo(
    () => resolveIndicatorGate(resultIndicator, successIndicator),
    [resultIndicator, successIndicator],
  );

  const initialReturnPhase = useMemo(
    () =>
      resolveInitialReturnPhase({
        indicatorGate,
        paymentTransactionId,
        resultIndicator,
        orderId,
        merchantReference: merchantReferenceParam,
      }),
    [indicatorGate, merchantReferenceParam, orderId, paymentTransactionId, resultIndicator],
  );

  const shouldReconcile = shouldReconcileReturn({ indicatorGate, paymentTransactionId });

  const [phase, setPhase] = useState<ReturnPhase>(initialReturnPhase);
  const [refreshError, setRefreshError] = useState<string | null>(null);

  const fallbackHref = useMemo(() => {
    if (orderId) {
      return `/orders/${orderId}`;
    }

    if (paymentTransactionId) {
      return `/payments/${encodeURIComponent(paymentTransactionId)}`;
    }

    return "/orders";
  }, [orderId, paymentTransactionId]);

  const stopPolling = useCallback(() => {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
  }, []);

  const reconcileOnce = useCallback(
    async (options?: { initial?: boolean }) => {
      if (!paymentTransactionId) {
        return null;
      }

      const token = getCustomerApiToken();
      if (!token) {
        stopPolling();
        setPhase("needs_auth");
        return null;
      }

      if (options?.initial) {
        setPhase("confirming");
      }

      try {
        const transaction = await refreshPaymentTransaction(paymentTransactionId, token);
        setRefreshError(null);

        const nextPhase = resolvePhaseAfterTransaction(transaction, "confirming");

        if (nextPhase === "redirecting") {
          stopPolling();
          setPhase("redirecting");
          clearNmbCheckoutContext();
          router.replace(buildSuccessHref(transaction, orderId, localOrderId));
          return transaction;
        }

        if (nextPhase === "failed") {
          stopPolling();
          setPhase("failed");
          return transaction;
        }

        setPhase("confirming");
        return transaction;
      } catch (error) {
        setRefreshError(
          error instanceof PaymentOrchestratorApiError
            ? error.message
            : "Unable to confirm payment with NMB.",
        );

        setPhase((current) => resolvePhaseAfterRefreshError(current));
        return null;
      }
    },
    [localOrderId, orderId, paymentTransactionId, router, stopPolling],
  );

  useEffect(() => {
    if (indicatorGate === "failed") {
      setPhase("indicator_failed");
    }
  }, [indicatorGate]);

  useEffect(() => {
    if (paymentTransactionId || resolutionStartedRef.current) {
      return;
    }

    if (
      !shouldAttemptReturnResolution({
        paymentTransactionId,
        resultIndicator,
        orderId,
        merchantReference: merchantReferenceParam,
      })
    ) {
      return;
    }

    resolutionStartedRef.current = true;
    setPhase("confirming");

    async function resolveTransactionId() {
      const token = getCustomerApiToken();
      if (!token) {
        setPhase("needs_auth");
        return;
      }

      try {
        const transaction = await resolvePaymentReturnTransaction(
          {
            orderId: looksLikeMerchantReference(orderId) ? null : orderId,
            merchantReference:
              merchantReferenceParam ??
              (looksLikeMerchantReference(orderId) ? orderId : null),
          },
          token,
        );

        setResolvedTransactionId(transaction.id);
        setRefreshError(null);

        const nextPhase = resolvePhaseAfterTransaction(transaction, "confirming");
        if (nextPhase === "redirecting") {
          setPhase("redirecting");
          clearNmbCheckoutContext();
          router.replace(buildSuccessHref(transaction, orderId, localOrderId));
          return;
        }

        if (nextPhase === "failed") {
          setPhase("failed");
          return;
        }

        setPhase("confirming");
      } catch (error) {
        setRefreshError(
          error instanceof PaymentOrchestratorApiError
            ? error.message
            : "Unable to resolve your payment return context.",
        );
        setPhase("confirming");
      }
    }

    void resolveTransactionId();
  }, [
    localOrderId,
    merchantReferenceParam,
    orderId,
    paymentTransactionId,
    resultIndicator,
    router,
  ]);

  useEffect(() => {
    if (!shouldReconcile || reconciliationStartedRef.current) {
      return;
    }

    reconciliationStartedRef.current = true;
    void reconcileOnce({ initial: true });
  }, [reconcileOnce, shouldReconcile]);

  useEffect(() => {
    stopPolling();

    if (!shouldReconcile || phase !== "confirming") {
      return;
    }

    pollRef.current = setInterval(() => {
      void reconcileOnce();
    }, POLL_INTERVAL_MS);

    return stopPolling;
  }, [phase, reconcileOnce, shouldReconcile, stopPolling]);

  const copy = getReturnPhaseCopy(phase);

  return (
    <div className="mx-auto max-w-lg rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
      {phase === "confirming" ? (
        <>
          <div className="mx-auto mb-4 h-10 w-10 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-900" />
          <h1 className="text-xl font-semibold text-zinc-900">{copy.title}</h1>
          <p className="mt-3 text-sm leading-6 text-zinc-600">{copy.body}</p>
          {refreshError ? (
            <p className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
              {refreshError}
            </p>
          ) : null}
          <Link
            href={fallbackHref}
            className="mt-6 inline-flex rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white"
          >
            View payment status
          </Link>
        </>
      ) : phase === "redirecting" ? (
        <>
          <h1 className="text-xl font-semibold text-zinc-900">{copy.title}</h1>
          <p className="mt-3 text-sm leading-6 text-zinc-600">{copy.body}</p>
        </>
      ) : phase === "failed" || phase === "indicator_failed" ? (
        <>
          <h1 className="text-xl font-semibold text-zinc-900">Payment failed</h1>
          <p className="mt-3 text-sm leading-6 text-zinc-600">
            {phase === "indicator_failed"
              ? "The payment result from NMB could not be verified. You can return to checkout and try again."
              : copy.body}
          </p>
          <Link
            href="/checkout/payment"
            className="mt-6 inline-flex rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white"
          >
            Retry payment
          </Link>
        </>
      ) : phase === "needs_auth" ? (
        <>
          <h1 className="text-xl font-semibold text-zinc-900">{copy.title}</h1>
          <p className="mt-3 text-sm leading-6 text-zinc-600">{copy.body}</p>
          <Link
            href={`/login?returnUrl=${encodeURIComponent(
              `/payment/return${searchParams.toString() ? `?${searchParams.toString()}` : ""}`,
            )}`}
            className="mt-6 inline-flex rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white"
          >
            Sign in
          </Link>
        </>
      ) : (
        <>
          <h1 className="text-xl font-semibold text-zinc-900">{copy.title}</h1>
          <p className="mt-3 text-sm leading-6 text-zinc-600">{copy.body}</p>
          <Link
            href={fallbackHref}
            className="mt-6 inline-flex rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white"
          >
            View order
          </Link>
        </>
      )}
    </div>
  );
}
