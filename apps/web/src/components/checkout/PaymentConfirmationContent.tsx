"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { motion, AnimatePresence } from "framer-motion";
import { useCart } from "@/lib/cart/context";
import { lockCartForOrder } from "@/lib/checkout/completion";
import { clearCheckoutDraft } from "@/lib/checkout/draft";
import { formatPrice } from "@/lib/catalog/utils";
import { PAYMENT_VERIFY_POLL_MS } from "@/lib/payment/constants";
import { paymentService } from "@/lib/payment/PaymentService";
import {
  clearPaymentTransaction,
  getPaymentTransaction,
} from "@/lib/payment/payment-session";
import { useOrderById } from "@/lib/order/use-order-by-id";
import { isOrderPaymentFailed, isOrderPaymentPaid } from "@/lib/order/placement";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { OrderSummaryTotals } from "@/components/cart/OrderSummaryTotals";
import { OrderSuccessItemsList } from "@/components/order/OrderSuccessItemsList";
import { CopyOrderNumber } from "@/components/order/CopyOrderNumber";
import { usePaymentTestMode } from "@/hooks/use-payment-test-mode";
import { TestModeBanner } from "@/components/payment/TestModeBanner";
import { SimulatePaymentButton } from "@/components/payment/SimulatePaymentButton";

interface PaymentConfirmationContentProps {
  orderId: string;
}

type ConfirmationPhase = "pending" | "paid" | "failed";

export function PaymentConfirmationContent({ orderId }: PaymentConfirmationContentProps) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { clearPurchasedItems } = useCart();
  const { order, isLoading } = useOrderById(orderId, { subscribe: true });
  const [transactionId, setTransactionId] = useState<string | null>(null);
  const [statusMessage, setStatusMessage] = useState<string>(
    "Sending STK Push to your phone…",
  );
  const [phase, setPhase] = useState<ConfirmationPhase>("pending");
  const [modeLabel, setModeLabel] = useState<string | null>(null);
  const [isSimulating, setIsSimulating] = useState(false);
  const pollingRef = useRef(false);
  const { simulateEnabled, testMode } = usePaymentTestMode();

  useEffect(() => {
    const fromQuery = searchParams.get("transactionId");
    const fromSession = getPaymentTransaction(orderId);
    setTransactionId(fromQuery ?? fromSession);
  }, [orderId, searchParams]);

  useEffect(() => {
    if (!order) {
      return;
    }

    lockCartForOrder(order.id, clearPurchasedItems);

    if (isOrderPaymentPaid(order)) {
      setPhase("paid");
      clearPaymentTransaction(orderId);
      clearCheckoutDraft();
    } else if (isOrderPaymentFailed(order)) {
      setPhase("failed");
    }
  }, [clearPurchasedItems, order, orderId]);

  const pollPayment = useCallback(async () => {
    if (!transactionId || pollingRef.current || phase !== "pending") {
      return;
    }

    pollingRef.current = true;

    try {
      const result = await paymentService.verifyPayment(transactionId);
      setModeLabel(result.mode === "test" ? "Test mode" : "Live M-Pesa");
      setStatusMessage(result.message);

      if (result.status === "paid") {
        setPhase("paid");
        clearPaymentTransaction(orderId);
        clearCheckoutDraft();
      } else if (result.status === "failed") {
        setPhase("failed");
      }
    } catch (error) {
      setStatusMessage(
        error instanceof Error ? error.message : "Unable to verify payment status.",
      );
    } finally {
      pollingRef.current = false;
    }
  }, [orderId, phase, transactionId]);

  const handleSimulatePayment = useCallback(async () => {
    if (!order || isSimulating || phase === "paid") {
      return;
    }

    setIsSimulating(true);
    setStatusMessage("Simulating test payment…");

    try {
      const paidOrder = await paymentService.simulatePayment(order);
      setPhase("paid");
      setStatusMessage("Test payment completed successfully.");
      clearPaymentTransaction(orderId);
      clearCheckoutDraft();
      lockCartForOrder(paidOrder.id, clearPurchasedItems);
    } catch (error) {
      setStatusMessage(
        error instanceof Error ? error.message : "Simulated payment failed.",
      );
      setPhase("failed");
    } finally {
      setIsSimulating(false);
    }
  }, [clearPurchasedItems, isSimulating, order, orderId, phase]);

  useEffect(() => {
    if (!transactionId || phase !== "pending") {
      return;
    }

    void pollPayment();
    const intervalId = setInterval(() => {
      void pollPayment();
    }, PAYMENT_VERIFY_POLL_MS);

    return () => clearInterval(intervalId);
  }, [pollPayment, phase, transactionId]);

  if (isLoading || !order) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-16 sm:px-6" aria-busy="true">
        <div className="mx-auto h-16 w-16 animate-pulse rounded-full bg-zinc-100" />
        <div className="mx-auto mt-6 h-8 w-56 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-10 h-80 animate-pulse rounded-3xl bg-zinc-50" />
      </div>
    );
  }

  const trackOrderHref = `/track-order/${orderId}`;
  const retryHref = `/checkout/payment`;

  return (
    <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
      {testMode && simulateEnabled ? <TestModeBanner className="mb-6" /> : null}

      <AnimatePresence mode="wait">
        <motion.div
          key={phase}
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -8 }}
          transition={{ duration: 0.3 }}
          className="text-center"
        >
          {phase === "pending" ? (
            <>
              <div className="relative mx-auto flex h-20 w-20 items-center justify-center">
                <span className="absolute inset-0 animate-ping rounded-full bg-[#c9a227]/20" />
                <span className="relative flex h-20 w-20 items-center justify-center rounded-full bg-[#c9a227]/15 text-3xl">
                  📱
                </span>
              </div>
              <p className="mt-6 text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
                M-Pesa Payment
              </p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
                Check your phone
              </h1>
              <p className="mx-auto mt-3 max-w-md text-sm leading-relaxed text-zinc-500">
                {statusMessage}
              </p>
              {modeLabel ? (
                <p className="mt-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">
                  {modeLabel}
                </p>
              ) : null}
            </>
          ) : null}

          {phase === "paid" ? (
            <>
              <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 text-3xl text-emerald-600">
                ✓
              </div>
              <p className="mt-6 text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">
                Payment Successful
              </p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
                Order Confirmed
              </h1>
              <p className="mx-auto mt-3 max-w-md text-sm leading-relaxed text-zinc-500">
                Your M-Pesa payment was received. We&apos;re preparing your order for shipment.
              </p>
            </>
          ) : null}

          {phase === "failed" ? (
            <>
              <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-red-100 text-3xl text-red-600">
                ✕
              </div>
              <p className="mt-6 text-xs font-bold uppercase tracking-[0.16em] text-red-700">
                Payment Failed
              </p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
                Couldn&apos;t complete payment
              </h1>
              <p className="mx-auto mt-3 max-w-md text-sm leading-relaxed text-zinc-500">
                {statusMessage || "The STK request was cancelled or timed out. You can retry payment."}
              </p>
            </>
          ) : null}
        </motion.div>
      </AnimatePresence>

      <article className="mt-10 space-y-6 rounded-3xl border border-zinc-200/80 bg-white p-6 shadow-[0_4px_24px_rgba(0,0,0,0.06)] sm:p-8">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <CopyOrderNumber orderNumber={order.orderNumber} />
          <PaymentStatusBadge
            status={
              phase === "paid"
                ? "paid"
                : phase === "failed"
                  ? "failed"
                  : order.paymentStatus
            }
          />
        </div>

        <section aria-labelledby="payment-summary-heading" className="space-y-4">
          <h2
            id="payment-summary-heading"
            className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500"
          >
            Order Summary
          </h2>
          <OrderSuccessItemsList items={order.items} />
          <OrderSummaryTotals totals={order.totals} />
          <p className="text-right text-sm font-bold text-zinc-900">
            Total paid: {formatPrice(order.totals.grandTotal)}
          </p>
        </section>

        {transactionId ? (
          <p className="text-center text-xs text-zinc-400">
            Transaction ref: <span className="font-mono">{transactionId.slice(0, 18)}…</span>
          </p>
        ) : null}

        <div className="flex flex-col gap-3 border-t border-zinc-100 pt-6 sm:flex-row">
          {phase === "paid" ? (
            <>
              <Link
                href={`/order-success/${orderId}`}
                className="inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/25"
              >
                View Confirmation
              </Link>
              <Link
                href={trackOrderHref}
                className="inline-flex flex-1 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-50"
              >
                Track Order
              </Link>
            </>
          ) : null}

          {phase === "failed" ? (
            <>
              <Link
                href={retryHref}
                className="inline-flex flex-1 items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3 text-sm font-bold text-zinc-900 shadow-lg shadow-[#c9a227]/25"
              >
                Retry Payment
              </Link>
              <button
                type="button"
                onClick={() => router.push(`/order-success/${orderId}`)}
                className="inline-flex flex-1 items-center justify-center rounded-xl border border-zinc-200 bg-white px-5 py-3 text-sm font-semibold text-zinc-700 hover:bg-zinc-50"
              >
                View Order Details
              </button>
            </>
          ) : null}

          {phase === "pending" ? (
            <div className="flex w-full flex-col gap-3">
              {simulateEnabled ? (
                <SimulatePaymentButton
                  onClick={handleSimulatePayment}
                  isLoading={isSimulating}
                  disabled={isSimulating}
                />
              ) : null}
              <p className="text-center text-xs text-zinc-400">
                {simulateEnabled
                  ? "Or wait for STK Push confirmation — do not close this page."
                  : "Do not close this page until payment is confirmed."}
              </p>
            </div>
          ) : null}
        </div>
      </article>
    </div>
  );
}
