"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCart } from "@/lib/cart/context";
import { lockCartForOrder } from "@/lib/checkout/completion";
import { clearCheckoutDraft, getCheckoutDraft } from "@/lib/checkout/draft";
import type { CheckoutDraft } from "@/lib/checkout/draft";
import {
  acquireDraftSubmissionLock,
  getOrderIdForDraft,
  releaseDraftSubmissionLock,
} from "@/lib/checkout/idempotency";
import type { Order } from "@/lib/types/order";
import type { PaymentMethodCode } from "@/lib/types/payment";
import { PAYMENT_METHOD_CODES, PAYMENT_STATUS } from "@/lib/types/payment";
import { PAYMENT_METHOD_LABELS } from "@/lib/payment/constants";
import { paymentService } from "@/lib/payments/checkout-service";
import {
  consumeNmbPendingPaymentId,
  saveNmbCheckoutContext,
} from "@/lib/nmb";
import { getOrderById as getStoredOrderById } from "@/lib/payment/order-storage";
import {
  getPaymentTransaction,
} from "@/lib/payment/payment-session";
import { redirectToPaymentProcessing } from "@/lib/payment/stk-flow";
import { shouldRedirectToOrderSuccess } from "@/lib/order/placement";
import { isGatewayPaymentMethod } from "@/lib/payment/payment-outcome";
import { usePaymentTestMode } from "@/hooks/use-payment-test-mode";
import { CheckoutSection } from "./CheckoutSection";
import { CheckoutOrderSummary } from "./CheckoutOrderSummary";
import { CheckoutStepIndicator } from "./CheckoutStepIndicator";
import { SimplifiedPaymentMethodSelector } from "@/components/payment/SimplifiedPaymentMethodSelector";
import { TestModeBanner } from "@/components/payment/TestModeBanner";
import { SimulatePaymentButton } from "@/components/payment/SimulatePaymentButton";

function redirectToOrderSuccess(router: ReturnType<typeof useRouter>, orderId: string): void {
  clearCheckoutDraft();
  router.replace(`/order-success/${orderId}`);
}

function finishOrder(
  order: Order,
  clearPurchasedItems: () => void,
  router: ReturnType<typeof useRouter>,
): void {
  lockCartForOrder(order.id, clearPurchasedItems);
  redirectToOrderSuccess(router, order.id);
}

export function PaymentPageContent() {
  const router = useRouter();
  const { clearPurchasedItems } = useCart();
  const [draft, setDraft] = useState<CheckoutDraft | null>(null);
  const [isReady, setIsReady] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethodCode | null>(null);
  const [paymentError, setPaymentError] = useState<string | undefined>();
  const [submitError, setSubmitError] = useState<string | undefined>();
  const [failedOrder, setFailedOrder] = useState<Order | null>(null);
  const [isProcessingPayment, setIsProcessingPayment] = useState(false);
  const [isSimulating, setIsSimulating] = useState(false);
  const paymentLockRef = useRef(false);
  const mountedRef = useRef(false);
  const { simulateEnabled, testMode } = usePaymentTestMode();

  useEffect(() => {
    if (mountedRef.current) {
      return;
    }
    mountedRef.current = true;

    const savedDraft = getCheckoutDraft();
    if (!savedDraft || savedDraft.items.length === 0) {
      router.replace("/checkout");
      return;
    }

    const existingOrderId = getOrderIdForDraft(savedDraft.draftId);
    if (existingOrderId) {
      const existing = getStoredOrderById(existingOrderId);
      if (existing) {
        if (shouldRedirectToOrderSuccess(existing)) {
          finishOrder(existing, clearPurchasedItems, router);
          return;
        }

        if (
          isGatewayPaymentMethod(existing.paymentMethod) &&
          existing.paymentStatus === PAYMENT_STATUS.PENDING
        ) {
          const transactionId =
            existing.paymentTransactionId ?? getPaymentTransaction(existing.id);
          if (transactionId) {
            clearCheckoutDraft();
            redirectToPaymentProcessing(router, existing.id, transactionId);
            return;
          }
        }

        lockCartForOrder(existing.id, clearPurchasedItems);
        setFailedOrder(existing);
      }
    }

    setDraft(savedDraft);
    setIsReady(true);
  }, [clearPurchasedItems, router]);

  const handleSubmit = useCallback(async () => {
    if (paymentLockRef.current || isProcessingPayment || !draft) {
      return;
    }

    if (!paymentMethod) {
      setPaymentError("Please select a payment method.");
      return;
    }

    if (draft.items.length === 0) {
      setSubmitError("Your cart is empty. Add items before placing an order.");
      return;
    }

    const existingOrderId = getOrderIdForDraft(draft.draftId);
    if (existingOrderId) {
      const existing = getStoredOrderById(existingOrderId);
      if (existing && shouldRedirectToOrderSuccess(existing)) {
        finishOrder(existing, clearPurchasedItems, router);
        return;
      }
    }

    if (!acquireDraftSubmissionLock(draft.draftId)) {
      const lockedOrderId = getOrderIdForDraft(draft.draftId);
      if (lockedOrderId) {
        const lockedOrder = getStoredOrderById(lockedOrderId);
        if (lockedOrder && shouldRedirectToOrderSuccess(lockedOrder)) {
          finishOrder(lockedOrder, clearPurchasedItems, router);
        }
      }
      return;
    }

    setPaymentError(undefined);
    setFailedOrder(null);
    paymentLockRef.current = true;
    setIsProcessingPayment(true);
    setSubmitError(undefined);

    try {
      const order = await paymentService.createOrder({
        customer: draft.customer,
        shippingAddress: draft.shippingAddress,
        orderNotes: draft.orderNotes,
        items: draft.items,
        totals: draft.totals,
        paymentMethod,
        cartSnapshot: draft.cartSnapshot,
        shippingMethod: draft.shippingMethod,
        itemShippingBreakdown: draft.itemShippingBreakdown,
        idempotencyKey: draft.draftId,
      });

      lockCartForOrder(order.id, clearPurchasedItems);

      if (paymentMethod === PAYMENT_METHOD_CODES.NMB) {
        const paymentId = consumeNmbPendingPaymentId();

        if (!paymentId) {
          releaseDraftSubmissionLock(draft.draftId);
          paymentLockRef.current = false;
          setIsProcessingPayment(false);
          setSubmitError(
            "NMB card payment requires a prepared payment session. Sign in and complete checkout through the store before paying with NMB.",
          );
          return;
        }

        saveNmbCheckoutContext({
          paymentId,
          localOrderId: order.id,
        });
        clearCheckoutDraft();
        router.push(`/checkout/payment/nmb/${paymentId}`);
        return;
      }

      if (isGatewayPaymentMethod(paymentMethod)) {
        const { transactionId } = await paymentService.beginStkPaymentProcessing(order);
        clearCheckoutDraft();
        redirectToPaymentProcessing(router, order.id, transactionId);
        return;
      }

      finishOrder(order, clearPurchasedItems, router);
    } catch (error) {
      releaseDraftSubmissionLock(draft.draftId);
      paymentLockRef.current = false;

      const linkedOrderId = getOrderIdForDraft(draft.draftId);
      if (linkedOrderId) {
        const linkedOrder = getStoredOrderById(linkedOrderId);
        if (linkedOrder) {
          lockCartForOrder(linkedOrder.id, clearPurchasedItems);
          setFailedOrder(linkedOrder);
        }
      }

      const message =
        error instanceof Error ? error.message : "We couldn't place your order. Please try again.";
      setSubmitError(message);
      setIsProcessingPayment(false);
    }
  }, [clearPurchasedItems, draft, isProcessingPayment, paymentMethod, router]);

  const handleSimulatePayment = useCallback(async () => {
    if (paymentLockRef.current || isProcessingPayment || isSimulating || !draft) {
      return;
    }

    if (draft.items.length === 0) {
      setSubmitError("Your cart is empty. Add items before placing an order.");
      return;
    }

    const existingOrderId = getOrderIdForDraft(draft.draftId);
    if (existingOrderId) {
      const existing = getStoredOrderById(existingOrderId);
      if (existing && shouldRedirectToOrderSuccess(existing)) {
        finishOrder(existing, clearPurchasedItems, router);
        return;
      }
    }

    if (!acquireDraftSubmissionLock(draft.draftId)) {
      return;
    }

    setPaymentError(undefined);
    setFailedOrder(null);
    setSubmitError(undefined);
    paymentLockRef.current = true;
    setIsSimulating(true);

    try {
      const order = await paymentService.createOrder({
        customer: draft.customer,
        shippingAddress: draft.shippingAddress,
        orderNotes: draft.orderNotes,
        items: draft.items,
        totals: draft.totals,
        paymentMethod: PAYMENT_METHOD_CODES.MPESA,
        cartSnapshot: draft.cartSnapshot,
        shippingMethod: draft.shippingMethod,
        itemShippingBreakdown: draft.itemShippingBreakdown,
        idempotencyKey: draft.draftId,
      });

      lockCartForOrder(order.id, clearPurchasedItems);

      const { transactionId } = await paymentService.beginStkPaymentProcessing(order);
      clearCheckoutDraft();
      redirectToPaymentProcessing(router, order.id, transactionId, { simulated: true });
    } catch (error) {
      releaseDraftSubmissionLock(draft.draftId);
      paymentLockRef.current = false;

      const linkedOrderId = getOrderIdForDraft(draft.draftId);
      if (linkedOrderId) {
        const linkedOrder = getStoredOrderById(linkedOrderId);
        if (linkedOrder) {
          lockCartForOrder(linkedOrder.id, clearPurchasedItems);
          setFailedOrder(linkedOrder);
        }
      }

      const message =
        error instanceof Error ? error.message : "Simulated payment failed. Please try again.";
      setSubmitError(message);
      setIsSimulating(false);
    }
  }, [clearPurchasedItems, draft, isProcessingPayment, isSimulating, router]);

  const handlePaymentChange = (code: PaymentMethodCode) => {
    if (isProcessingPayment) {
      return;
    }
    setPaymentMethod(code);
    setPaymentError(undefined);
  };

  if (!isReady || !draft) {
    return (
      <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div className="h-8 w-56 animate-pulse rounded-lg bg-zinc-100" />
        <div className="mt-8 grid gap-6 lg:grid-cols-[1fr_400px]">
          <div className="h-96 animate-pulse rounded-3xl bg-zinc-50" />
          <div className="h-80 animate-pulse rounded-3xl bg-zinc-50" />
        </div>
      </div>
    );
  }

  const submitLabel = (() => {
    if (isGatewayPaymentMethod(paymentMethod)) {
      if (isProcessingPayment) {
        return "Processing payment…";
      }
      if (failedOrder) {
        return `Retry ${PAYMENT_METHOD_LABELS[paymentMethod ?? ""] ?? "Payment"}`;
      }
      const labels: Record<string, string> = {
        mpesa: "Pay with M-Pesa",
        nmb: "Pay with NMB",
        selcom: "Pay with Selcom",
      };
      return labels[paymentMethod ?? ""] ?? "Pay now";
    }

    if (paymentMethod === PAYMENT_METHOD_CODES.COD) {
      return isProcessingPayment
        ? "Placing order…"
        : failedOrder
          ? "Retry Order (COD)"
          : "Place Order (COD)";
    }

    return isProcessingPayment
      ? "Placing order…"
      : failedOrder
        ? "Retry Order"
        : "Place Order";
  })();

  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">
            Secure checkout
          </p>
          <h1 className="mt-1.5 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            Payment
          </h1>
          <p className="mt-2 max-w-xl text-sm leading-relaxed text-zinc-500">
            Review your total and choose how you&apos;d like to pay. Shipping details are already
            saved.
          </p>
        </div>
        {!isProcessingPayment ? (
          <Link
            href="/checkout"
            className="text-sm font-semibold text-[#8b6914] transition hover:text-[#c9a227]"
          >
            Edit shipping details
          </Link>
        ) : null}
      </div>

      <CheckoutStepIndicator current="payment" />

      {testMode && simulateEnabled ? <TestModeBanner className="mt-6" /> : null}

      {failedOrder ? (
        <div
          role="alert"
          className="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-4 sm:px-5"
        >
          <p className="text-sm font-semibold text-red-800">Previous payment attempt failed</p>
          <p className="mt-1 text-sm text-red-700">
            Order <span className="font-mono font-semibold">[{failedOrder.orderNumber}]</span> was
            created but payment did not go through. Your cart is locked — select a payment method
            and try again.
          </p>
        </div>
      ) : null}

      <div className="mt-8 grid gap-8 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start">
        <fieldset disabled={isProcessingPayment} className="space-y-6 border-0 p-0">
          <CheckoutSection
            title="Payment Method"
            description="Select how you want to pay for this order."
          >
            <SimplifiedPaymentMethodSelector
              value={paymentMethod}
              onChange={handlePaymentChange}
              error={paymentError}
              disabled={isProcessingPayment}
            />
          </CheckoutSection>

          {submitError ? (
            <p
              role="alert"
              className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
            >
              {submitError}
            </p>
          ) : null}

          {simulateEnabled ? (
            <CheckoutSection
              title="Developer Test Tools"
              description="Skip real payment and complete checkout instantly."
            >
              <SimulatePaymentButton
                onClick={handleSimulatePayment}
                disabled={isProcessingPayment}
                isLoading={isSimulating}
              />
              <p className="mt-2 text-xs leading-relaxed text-zinc-500">
                Marks the order as processing, simulates STK Push timing (~2.5s), then confirms
                payment — safe for local testing only.
              </p>
            </CheckoutSection>
          ) : null}
        </fieldset>

        <CheckoutOrderSummary
          items={draft.items}
          totals={draft.totals}
          onSubmit={handleSubmit}
          isSubmitting={isProcessingPayment || isSimulating}
          submitDisabled={isProcessingPayment || isSimulating}
          submitLabel={submitLabel}
          submitHint={
            isProcessingPayment || isSimulating
              ? isGatewayPaymentMethod(paymentMethod) || isSimulating
                ? "Redirecting to payment processing…"
                : "Processing — please do not refresh or click again"
              : failedOrder
                ? "Retry payment — your order is already saved"
                : "Payment step — shipping already confirmed"
          }
          backHref="/checkout"
          backLabel="← Back to checkout"
        />
      </div>
    </div>
  );
}
