"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { formatPrice } from "@/lib/catalog/utils";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  PaymentOrchestratorApiError,
  fetchPaymentTransaction,
  parsePaymentAmount,
  refreshPaymentTransaction,
  startPaymentTransaction,
  type PaymentTransactionPayload,
} from "@/lib/api/customer-payment-orchestrator";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";

const STATUS_STYLES: Record<string, string> = {
  pending: "bg-amber-50 text-amber-800 ring-amber-600/20",
  processing: "bg-blue-50 text-blue-800 ring-blue-600/20",
  successful: "bg-green-50 text-green-800 ring-green-600/20",
  failed: "bg-red-50 text-red-700 ring-red-600/20",
  cancelled: "bg-zinc-100 text-zinc-700 ring-zinc-300/40",
};

const WAITING_STATUSES = new Set(["pending", "processing"]);
const POLL_INTERVAL_MS = 4000;

interface PaymentOrchestratorPageProps {
  transactionId?: string;
  orderId?: string;
}

export function PaymentOrchestratorPage({
  transactionId,
  orderId,
}: PaymentOrchestratorPageProps) {
  const router = useRouter();
  const [transaction, setTransaction] = useState<PaymentTransactionPayload | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [needsAuth, setNeedsAuth] = useState(false);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const stopPolling = useCallback(() => {
    if (pollRef.current) {
      clearInterval(pollRef.current);
      pollRef.current = null;
    }
  }, []);

  const load = useCallback(async () => {
    const token = getCustomerApiToken();
    if (!token) {
      setNeedsAuth(true);
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(null);

    try {
      if (transactionId) {
        const next = await fetchPaymentTransaction(transactionId, token);
        setTransaction(next);
      } else if (orderId) {
        const next = await startPaymentTransaction(orderId, "nmb", token);
        setTransaction(next);
        router.replace(`/payments/${encodeURIComponent(next.id)}`);
      } else {
        setError("Missing payment transaction.");
      }
    } catch (err) {
      if (err instanceof PaymentOrchestratorApiError && err.statusCode === 401) {
        setNeedsAuth(true);
      } else {
        setError(
          err instanceof PaymentOrchestratorApiError
            ? err.message
            : "Unable to load payment.",
        );
      }
    } finally {
      setLoading(false);
    }
  }, [orderId, router, transactionId]);

  useEffect(() => {
    void load();
  }, [load]);

  // Poll while waiting for NMB callback / verification.
  useEffect(() => {
    stopPolling();

    const token = getCustomerApiToken();
    if (!token || !transaction || !WAITING_STATUSES.has(transaction.status)) {
      return;
    }

    pollRef.current = setInterval(() => {
      void (async () => {
        try {
          const next = await refreshPaymentTransaction(transaction.id, token);
          setTransaction(next);
          if (!WAITING_STATUSES.has(next.status)) {
            stopPolling();
          }
        } catch {
          // Keep waiting UI; user can refresh manually.
        }
      })();
    }, POLL_INTERVAL_MS);

    return stopPolling;
  }, [stopPolling, transaction]);

  const handleRetry = async () => {
    const token = getCustomerApiToken();
    if (!token || !transaction || busy) return;

    setBusy(true);
    setError(null);

    try {
      if (transaction.status === "failed" || transaction.status === "cancelled") {
        const next = await startPaymentTransaction(transaction.order_id, "nmb", token);
        setTransaction(next);
        router.replace(`/payments/${encodeURIComponent(next.id)}`);
      } else {
        const next = await refreshPaymentTransaction(transaction.id, token);
        setTransaction(next);
      }
    } catch (err) {
      setError(
        err instanceof PaymentOrchestratorApiError
          ? err.message
          : "Unable to retry payment.",
      );
    } finally {
      setBusy(false);
    }
  };

  if (needsAuth) {
    return (
      <div className="mx-auto max-w-lg px-4 py-12">
        <AuthInvitationCard context="checkout" returnUrl="/orders" />
      </div>
    );
  }

  if (loading) {
    return (
      <div className="mx-auto max-w-lg px-4 py-16 text-center text-sm text-zinc-500">
        Loading payment…
      </div>
    );
  }

  if (error && !transaction) {
    return (
      <div className="mx-auto max-w-lg px-4 py-12">
        <div className="rounded-2xl border border-red-200 bg-red-50 p-5" role="alert">
          <p className="text-sm font-semibold text-red-900">Payment error</p>
          <p className="mt-1 text-sm text-red-800">{error}</p>
          <Link href="/orders" className="mt-4 inline-block text-sm font-semibold text-red-900">
            Back to orders
          </Link>
        </div>
      </div>
    );
  }

  if (!transaction) {
    return null;
  }

  const amount = parsePaymentAmount(transaction.amount);
  const status = transaction.status;
  const orderNumber = transaction.order?.order_number ?? "—";
  const isWaiting = WAITING_STATUSES.has(status);
  const isSuccess = status === "successful";
  const isFailed = status === "failed" || status === "cancelled";

  return (
    <div className="mx-auto max-w-lg px-4 py-10 sm:px-6">
      <p className="text-xs font-semibold uppercase tracking-[0.16em] text-[#8b6914]">
        NMB Payment
      </p>
      <h1 className="mt-1 text-3xl font-bold tracking-tight text-zinc-900">
        {isSuccess ? "Payment successful" : isFailed ? "Payment unsuccessful" : "Complete payment"}
      </h1>
      <p className="mt-2 text-sm text-zinc-500">
        {isSuccess
          ? "Your order is marked as paid. Fulfillment will follow in a later step."
          : isFailed
            ? "The payment did not complete. You can try again from this order."
            : "Follow the NMB checkout instructions below. We will update this page when payment is confirmed."}
      </p>

      <section className="mt-8 rounded-3xl border border-zinc-200 bg-white p-5 shadow-sm sm:p-6">
        <dl className="space-y-4 text-sm">
          <div className="flex justify-between gap-4">
            <dt className="text-zinc-500">Order number</dt>
            <dd className="font-semibold text-zinc-900">{orderNumber}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-zinc-500">Amount</dt>
            <dd className="font-semibold text-zinc-900">
              {formatPrice(amount)} {transaction.currency}
            </dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-zinc-500">Provider</dt>
            <dd className="font-semibold uppercase text-zinc-900">{transaction.provider}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-zinc-500">Merchant reference</dt>
            <dd className="font-mono text-xs text-zinc-700">{transaction.merchant_reference}</dd>
          </div>
          {transaction.provider_reference ? (
            <div className="flex justify-between gap-4">
              <dt className="text-zinc-500">NMB session</dt>
              <dd className="font-mono text-xs text-zinc-700">{transaction.provider_reference}</dd>
            </div>
          ) : null}
          <div className="flex items-center justify-between gap-4">
            <dt className="text-zinc-500">Transaction status</dt>
            <dd>
              <span
                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ${
                  STATUS_STYLES[status] ?? "bg-zinc-50 text-zinc-700 ring-zinc-300/40"
                }`}
              >
                {status}
              </span>
            </dd>
          </div>
        </dl>

        {isWaiting ? (
          <div className="mt-5 rounded-2xl border border-blue-100 bg-blue-50/80 px-4 py-3">
            <p className="text-sm font-semibold text-blue-900">Waiting for NMB confirmation…</p>
            <p className="mt-1 text-sm text-blue-800">
              Complete payment in the NMB checkout. This page refreshes automatically.
            </p>
            {transaction.checkout_url ? (
              <a
                href={transaction.checkout_url}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-3 inline-flex w-full items-center justify-center rounded-xl bg-[#0b3d91] px-4 py-3 text-sm font-semibold text-white transition hover:bg-[#0a347c]"
              >
                Open NMB checkout
              </a>
            ) : (
              <p className="mt-2 text-xs text-blue-700">
                Session ready. Use your bank/NMB payment channel with merchant reference{" "}
                <span className="font-mono">{transaction.merchant_reference}</span>.
              </p>
            )}
          </div>
        ) : null}

        {isSuccess ? (
          <div className="mt-5 rounded-2xl border border-green-200 bg-green-50 px-4 py-3">
            <p className="text-sm font-semibold text-green-900">Payment verified</p>
            <p className="mt-1 text-sm text-green-800">
              {transaction.external_transaction_id
                ? `NMB transaction ${transaction.external_transaction_id}. `
                : ""}
              Your order status is now paid.
            </p>
          </div>
        ) : null}

        {isFailed ? (
          <div className="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3">
            <p className="text-sm font-semibold text-red-900">Payment failed</p>
            <p className="mt-1 text-sm text-red-800">
              No charge was completed for this attempt. Retry to start a new NMB session.
            </p>
          </div>
        ) : null}

        {error ? (
          <p className="mt-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">
            {error}
          </p>
        ) : null}

        <div className="mt-6 space-y-3">
          {!isSuccess ? (
            <button
              type="button"
              onClick={() => void handleRetry()}
              disabled={busy}
              className="flex w-full items-center justify-center rounded-xl bg-zinc-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50"
            >
              {busy
                ? "Working…"
                : isFailed
                  ? "Retry payment"
                  : "Check payment status"}
            </button>
          ) : null}
          <Link
            href={`/orders/${encodeURIComponent(orderNumber)}`}
            className="flex w-full items-center justify-center rounded-xl border border-zinc-200 py-3 text-sm font-semibold text-zinc-800 transition hover:border-[#c9a227]/40"
          >
            View order
          </Link>
        </div>
      </section>
    </div>
  );
}
