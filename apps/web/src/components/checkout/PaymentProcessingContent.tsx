"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { AnimatePresence, motion } from "framer-motion";
import { useCart } from "@/lib/cart/context";
import { clearCheckoutDraft } from "@/lib/checkout/draft";
import { lockCartForOrder } from "@/lib/checkout/completion";
import { formatPrice } from "@/lib/catalog/utils";
import { PAYMENT_SUCCESS_REDIRECT_MS, PAYMENT_VERIFY_POLL_MS } from "@/lib/payment/constants";
import { paymentService } from "@/lib/payments/checkout-service";
import {
  clearPaymentTransaction,
  getPaymentTransaction,
} from "@/lib/payment/payment-session";
import {
  getStkPhaseHeadline,
  getStkPhaseSubtext,
  orderSnapshotForProcessing,
  resolveStkFlowPhase,
  type StkFlowPhase,
} from "@/lib/payment/stk-flow";
import { logPaymentEvent } from "@/lib/payment/payment-logger";
import { useOrderById } from "@/lib/order/use-order-by-id";
import { isOrderPaymentFailed, isOrderPaymentPaid } from "@/lib/order/placement";
import { ORDER_STATUS } from "@/lib/types/order";
import { MpesaPaymentStatusHero } from "@/components/payment/MpesaPaymentStatusHero";
import { MpesaPaymentSuccessPanel } from "@/components/payment/MpesaPaymentSuccessPanel";
import { MpesaStkFlowSteps } from "@/components/payment/MpesaStkFlowSteps";
import { TestModeBanner } from "@/components/payment/TestModeBanner";
import { usePaymentTestMode } from "@/hooks/use-payment-test-mode";

interface PaymentProcessingContentProps {
  orderId: string;
}

export function PaymentProcessingContent({ orderId }: PaymentProcessingContentProps) {
  const router = useRouter();
  const searchParams = useSearchParams();
  const { clearPurchasedItems } = useCart();
  const { order, isLoading } = useOrderById(orderId, { subscribe: true, poll: true });
  const { testMode } = usePaymentTestMode();

  const [transactionId, setTransactionId] = useState<string | null>(null);
  const [paymentReference, setPaymentReference] = useState<string | null>(null);
  const [elapsedMs, setElapsedMs] = useState(0);
  const [paidElapsedMs, setPaidElapsedMs] = useState<number | null>(null);
  const [statusMessage, setStatusMessage] = useState<string | null>(null);
  const pollingRef = useRef(false);
  const redirectScheduledRef = useRef(false);
  const fulfillmentLockedRef = useRef(false);
  const paidTimerStartedRef = useRef(false);

  useEffect(() => {
    const fromQuery = searchParams.get("transactionId");
    const fromSession = getPaymentTransaction(orderId);
    setTransactionId(fromQuery ?? fromSession);
  }, [orderId, searchParams]);

  useEffect(() => {
    const startedAt = Date.now();
    const timer = setInterval(() => {
      setElapsedMs(Date.now() - startedAt);
    }, 100);
    return () => clearInterval(timer);
  }, []);

  useEffect(() => {
    if (!order) {
      return;
    }
    lockCartForOrder(order.id, clearPurchasedItems);
  }, [clearPurchasedItems, order]);

  const paymentPaid = order ? isOrderPaymentPaid(order) : false;
  const paymentFailed = order ? isOrderPaymentFailed(order) : false;

  useEffect(() => {
    if (!paymentPaid || paidTimerStartedRef.current) {
      return;
    }
    paidTimerStartedRef.current = true;
    const started = Date.now();
    setPaidElapsedMs(0);
    const tick = setInterval(() => {
      setPaidElapsedMs(Date.now() - started);
    }, 100);
    return () => clearInterval(tick);
  }, [paymentPaid]);

  const phase: StkFlowPhase = resolveStkFlowPhase({
    elapsedMs,
    paymentFailed,
    paymentPaid,
    msSincePaid: paidElapsedMs,
  });

  const headline = statusMessage ?? getStkPhaseHeadline(phase);
  const subtext = getStkPhaseSubtext(phase, testMode);

  const pollPayment = useCallback(async () => {
    if (!transactionId || pollingRef.current || paymentPaid || paymentFailed) {
      return;
    }

    pollingRef.current = true;

    try {
      const result = await paymentService.verifyPayment(transactionId);

      if (result.status === "paid") {
        setPaymentReference(result.paymentReference);
        logPaymentEvent("stk:confirmed", {
          orderId,
          transactionId,
          paymentReference: result.paymentReference,
        });
      } else if (result.status === "failed") {
        setStatusMessage(result.message);
        logPaymentEvent("stk:failed", { orderId, transactionId, message: result.message });
      }
    } catch (error) {
      setStatusMessage(
        error instanceof Error ? error.message : "Unable to verify payment status.",
      );
    } finally {
      pollingRef.current = false;
    }
  }, [orderId, paymentFailed, paymentPaid, transactionId]);

  useEffect(() => {
    if (!transactionId || paymentPaid || paymentFailed) {
      return;
    }

    void pollPayment();
    const intervalId = setInterval(() => {
      void pollPayment();
    }, PAYMENT_VERIFY_POLL_MS);

    return () => clearInterval(intervalId);
  }, [pollPayment, paymentFailed, paymentPaid, transactionId]);

  useEffect(() => {
    if (!order || !paymentPaid || fulfillmentLockedRef.current) {
      return;
    }

    fulfillmentLockedRef.current = true;

    if (order.status !== ORDER_STATUS.PROCESSING) {
      paymentService.updateOrderStatus(order.orderNumber, ORDER_STATUS.PROCESSING);
    }

    clearPaymentTransaction(orderId);
    clearCheckoutDraft();

    logPaymentEvent("stk:complete", {
      orderId: order.id,
      orderNumber: order.orderNumber,
      transactionId,
    });
  }, [order, orderId, paymentPaid, transactionId]);

  useEffect(() => {
    if (phase !== "success" || redirectScheduledRef.current) {
      return;
    }

    redirectScheduledRef.current = true;

    const timeoutId = setTimeout(() => {
      router.replace(`/order-success/${orderId}`);
    }, PAYMENT_SUCCESS_REDIRECT_MS);

    return () => clearTimeout(timeoutId);
  }, [orderId, phase, router]);

  if (isLoading || !order) {
    return (
      <div className="min-h-[70vh] bg-zinc-950 px-4 py-16 sm:px-6" aria-busy="true">
        <div className="mx-auto max-w-md">
          <div className="mx-auto h-24 w-24 animate-pulse rounded-full bg-zinc-800" />
          <div className="mx-auto mt-6 h-8 w-56 animate-pulse rounded-lg bg-zinc-800" />
          <div className="mt-10 h-64 animate-pulse rounded-2xl bg-zinc-900" />
        </div>
      </div>
    );
  }

  const snapshot = orderSnapshotForProcessing(order);
  const showSuccessPanel = phase === "success";
  const showSteps = phase !== "failed" && phase !== "success";

  return (
    <div className="min-h-[70vh] bg-zinc-950 px-4 py-8 sm:px-6 sm:py-12">
      <div className="mx-auto max-w-md">
        {testMode ? <TestModeBanner className="mb-6" variant="dark" /> : null}

        <AnimatePresence mode="wait">
          <MpesaPaymentStatusHero
            phase={phase}
            headline={headline}
            subtext={subtext}
            testMode={testMode}
          />
        </AnimatePresence>

        {showSuccessPanel ? (
          <MpesaPaymentSuccessPanel
            orderNumber={order.orderNumber}
            transactionId={transactionId}
            paymentReference={paymentReference ?? order.paymentReference}
            amount={order.totals.grandTotal}
          />
        ) : null}

        {showSteps ? (
          <motion.div
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2, duration: 0.35 }}
            className="mt-8 rounded-2xl border border-zinc-800 bg-zinc-900/80 p-5 shadow-xl shadow-black/20 backdrop-blur sm:p-6"
          >
            <MpesaStkFlowSteps phase={phase} />

            {testMode && phase === "waiting_pin" ? (
              <button
                type="button"
                onClick={() => void pollPayment()}
                className="mt-4 w-full rounded-xl border border-[#c9a227]/40 bg-[#c9a227]/10 px-4 py-2.5 text-sm font-semibold text-[#e8c547] transition hover:bg-[#c9a227]/20"
              >
                Check payment status
              </button>
            ) : null}

            {snapshot.orderProcessing && !paymentPaid ? (
              <p className="mt-4 text-center text-xs text-zinc-500">
                Order #{order.orderNumber.slice(-8)} is being prepared while payment confirms.
              </p>
            ) : null}
          </motion.div>
        ) : null}

        {phase === "failed" ? (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="mt-8 space-y-4"
          >
            <div className="rounded-2xl border border-zinc-800 bg-zinc-900/80 p-5 text-center">
              <p className="text-sm text-zinc-400">
                Total due:{" "}
                <span className="font-bold text-white">{formatPrice(order.totals.grandTotal)}</span>
              </p>
            </div>
            <Link
              href="/checkout/payment"
              className="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3.5 text-sm font-bold text-zinc-950 shadow-lg shadow-[#c9a227]/20 transition hover:brightness-105"
            >
              Retry Payment
            </Link>
          </motion.div>
        ) : null}

        {!showSuccessPanel && phase !== "failed" ? (
          <p className="mt-6 text-center text-xs text-zinc-600">
            Do not close this page until payment is confirmed.
          </p>
        ) : null}
      </div>
    </div>
  );
}
