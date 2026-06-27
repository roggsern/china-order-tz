"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { motion, AnimatePresence } from "framer-motion";
import { useCart } from "@/lib/cart/context";
import { clearCheckoutDraft } from "@/lib/checkout/draft";
import { lockCartForOrder } from "@/lib/checkout/completion";
import { formatPrice } from "@/lib/catalog/utils";
import { PAYMENT_VERIFY_POLL_MS, PAYMENT_SUCCESS_REDIRECT_MS } from "@/lib/payment/constants";
import { paymentService } from "@/lib/payment/PaymentService";
import {
  clearPaymentTransaction,
  getPaymentTransaction,
} from "@/lib/payment/payment-session";
import {
  orderSnapshotForProcessing,
  resolveStkVisualStep,
  type StkVisualStep,
} from "@/lib/payment/stk-flow";
import { logPaymentEvent } from "@/lib/payment/payment-logger";
import { useOrderById } from "@/lib/order/use-order-by-id";
import { isOrderPaymentFailed, isOrderPaymentPaid } from "@/lib/order/placement";
import { ORDER_STATUS } from "@/lib/types/order";
import { PaymentStatusBadge } from "@/components/payment/PaymentStatusBadge";
import { PaymentStkStepIndicator } from "@/components/payment/PaymentStkStepIndicator";
import { TestModeBanner } from "@/components/payment/TestModeBanner";
import { OrderSummaryTotals } from "@/components/cart/OrderSummaryTotals";
import { OrderSuccessItemsList } from "@/components/order/OrderSuccessItemsList";
import { CopyOrderNumber } from "@/components/order/CopyOrderNumber";
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
  const [elapsedMs, setElapsedMs] = useState(0);
  const [confirmingStarted, setConfirmingStarted] = useState(false);
  const [statusMessage, setStatusMessage] = useState("Waiting for M-Pesa STK Push...");
  const pollingRef = useRef(false);
  const redirectScheduledRef = useRef(false);
  const fulfillmentLockedRef = useRef(false);

  const isSimulated = searchParams.get("simulated") === "1";

  useEffect(() => {
    const fromQuery = searchParams.get("transactionId");
    const fromSession = getPaymentTransaction(orderId);
    setTransactionId(fromQuery ?? fromSession);
  }, [orderId, searchParams]);

  useEffect(() => {
    const startedAt = Date.now();
    const timer = setInterval(() => {
      setElapsedMs(Date.now() - startedAt);
    }, 200);
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

  const visualStep: StkVisualStep = resolveStkVisualStep({
    paymentFailed,
    paymentPaid,
    confirmingStarted,
    elapsedMs,
  });

  const pollPayment = useCallback(async () => {
    if (!transactionId || pollingRef.current || paymentPaid || paymentFailed) {
      return;
    }

    pollingRef.current = true;

    try {
      const result = await paymentService.verifyPayment(transactionId);

      if (result.status === "paid") {
        setConfirmingStarted(true);
        setStatusMessage("Payment confirmed. Finalizing your order…");
        logPaymentEvent("stk:confirmed", {
          orderId,
          transactionId,
          paymentReference: result.paymentReference,
        });
      } else if (result.status === "failed") {
        setStatusMessage(result.message);
        logPaymentEvent("stk:failed", { orderId, transactionId, message: result.message });
      } else if (elapsedMs >= 1200) {
        setStatusMessage("Enter your M-Pesa PIN on your phone to complete payment.");
      }
    } catch (error) {
      setStatusMessage(
        error instanceof Error ? error.message : "Unable to verify payment status.",
      );
    } finally {
      pollingRef.current = false;
    }
  }, [elapsedMs, orderId, paymentFailed, paymentPaid, transactionId]);

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

    setConfirmingStarted(true);
    setStatusMessage("Payment successful. Redirecting to confirmation…");
    clearPaymentTransaction(orderId);
    clearCheckoutDraft();

    logPaymentEvent("stk:complete", {
      orderId: order.id,
      orderNumber: order.orderNumber,
      transactionId,
    });
  }, [order, orderId, paymentPaid, transactionId]);

  useEffect(() => {
    if (!paymentPaid || redirectScheduledRef.current) {
      return;
    }

    redirectScheduledRef.current = true;

    const timeoutId = setTimeout(() => {
      router.replace(`/order-success/${orderId}`);
    }, PAYMENT_SUCCESS_REDIRECT_MS);

    return () => clearTimeout(timeoutId);
  }, [orderId, paymentPaid, router]);

  useEffect(() => {
    if (paymentFailed) {
      setStatusMessage("Payment was not completed. You can retry from the payment page.");
    }
  }, [paymentFailed]);

  if (isLoading || !order) {
    return (
      <div className="mx-auto max-w-2xl px-4 py-16 sm:px-6" aria-busy="true">
        <div className="mx-auto h-16 w-16 animate-pulse rounded-full bg-zinc-100" />
        <div className="mx-auto mt-6 h-8 w-56 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-10 h-80 animate-pulse rounded-3xl bg-zinc-50" />
      </div>
    );
  }

  const snapshot = orderSnapshotForProcessing(order);

  return (
    <div className="mx-auto max-w-2xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
      {testMode ? <TestModeBanner className="mb-6" /> : null}

      <AnimatePresence mode="wait">
        <motion.div
          key={visualStep}
          initial={{ opacity: 0, y: 10 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -8 }}
          transition={{ duration: 0.28 }}
          className="text-center"
        >
          {visualStep !== "failed" ? (
            <>
              <div className="relative mx-auto flex h-20 w-20 items-center justify-center">
                {visualStep !== "complete" && visualStep !== "confirming" ? (
                  <span className="absolute inset-0 animate-ping rounded-full bg-[#c9a227]/20" />
                ) : null}
                <span
                  className={`relative flex h-20 w-20 items-center justify-center rounded-full text-3xl ${
                    visualStep === "complete" || visualStep === "confirming"
                      ? "bg-emerald-100 text-emerald-600"
                      : "bg-[#c9a227]/15 text-[#8b6914]"
                  }`}
                >
                  {visualStep === "complete" || (visualStep === "confirming" && paymentPaid)
                    ? "✓"
                    : "📱"}
                </span>
              </div>

              <p className="mt-6 text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
                {isSimulated ? "Simulated STK Push" : "M-Pesa Payment"}
              </p>
              <h1 className="mt-2 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl">
                {paymentPaid
                  ? "Payment confirmed"
                  : visualStep === "processing"
                    ? "Processing payment"
                    : "Waiting for M-Pesa STK Push…"}
              </h1>
              <p className="mx-auto mt-3 max-w-md text-sm leading-relaxed text-zinc-500">
                {statusMessage}
              </p>

              {snapshot.orderProcessing && !paymentPaid ? (
                <p className="mt-2 text-xs font-medium text-zinc-400">
                  Order is being prepared while we confirm your payment.
                </p>
              ) : null}
            </>
          ) : (
            <>
              <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-red-100 text-3xl text-red-600">
                ✕
              </div>
              <h1 className="mt-6 text-2xl font-bold tracking-tight text-zinc-900">
                Payment failed
              </h1>
              <p className="mx-auto mt-3 max-w-md text-sm text-zinc-500">{statusMessage}</p>
            </>
          )}
        </motion.div>
      </AnimatePresence>

      <div className="mt-8 rounded-2xl border border-zinc-200/80 bg-white p-5 shadow-sm sm:p-6">
        <PaymentStkStepIndicator activeStep={visualStep} />
      </div>

      <article className="mt-6 space-y-6 rounded-3xl border border-zinc-200/80 bg-white p-6 shadow-[0_4px_24px_rgba(0,0,0,0.06)] sm:p-8">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <CopyOrderNumber orderNumber={order.orderNumber} />
          <PaymentStatusBadge status={order.paymentStatus} />
        </div>

        <section aria-labelledby="processing-summary-heading" className="space-y-4">
          <h2
            id="processing-summary-heading"
            className="text-xs font-bold uppercase tracking-[0.14em] text-zinc-500"
          >
            Order Summary
          </h2>
          <OrderSuccessItemsList items={order.items} />
          <OrderSummaryTotals totals={order.totals} />
          <p className="text-right text-sm font-bold text-zinc-900">
            Total: {formatPrice(order.totals.grandTotal)}
          </p>
        </section>

        {transactionId ? (
          <p className="text-center text-xs text-zinc-400">
            Transaction ref: <span className="font-mono">{transactionId.slice(0, 20)}…</span>
          </p>
        ) : null}

        {visualStep === "failed" ? (
          <Link
            href="/checkout/payment"
            className="inline-flex w-full items-center justify-center rounded-xl bg-gradient-to-r from-[#c9a227] to-[#e8c547] px-5 py-3 text-sm font-bold text-zinc-900"
          >
            Retry Payment
          </Link>
        ) : (
          <p className="text-center text-xs text-zinc-400">
            Please do not close this page until payment is confirmed.
          </p>
        )}
      </article>
    </div>
  );
}
