"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCart } from "@/lib/cart/context";
import { clearCartIfOrderPaid } from "@/lib/checkout/completion";
import { clearCheckoutDraft, getCheckoutDraft } from "@/lib/checkout/draft";
import type { CheckoutDraft } from "@/lib/checkout/draft";
import {
  acquireDraftSubmissionLock,
  getOrderIdForDraft,
  releaseDraftSubmissionLock,
} from "@/lib/checkout/idempotency";
import type { PaymentMethodCode } from "@/lib/types/payment";
import { PAYMENT_METHOD_CODES } from "@/lib/types/payment";
import { paymentService } from "@/lib/payment/PaymentService";
import { getOrderById as getStoredOrderById } from "@/lib/payment/order-storage";
import { CheckoutSection } from "./CheckoutSection";
import { CheckoutOrderSummary } from "./CheckoutOrderSummary";
import { CheckoutStepIndicator } from "./CheckoutStepIndicator";
import { SimplifiedPaymentMethodSelector } from "@/components/payment/SimplifiedPaymentMethodSelector";

function redirectToOrderSuccess(router: ReturnType<typeof useRouter>, orderId: string): void {
  clearCheckoutDraft();
  router.replace(`/order-success/${orderId}`);
}

function finishOrder(
  orderId: string,
  clearPurchasedItems: () => void,
  router: ReturnType<typeof useRouter>,
): void {
  clearCartIfOrderPaid(orderId, clearPurchasedItems);
  redirectToOrderSuccess(router, orderId);
}

export function PaymentPageContent() {
  const router = useRouter();
  const { clearPurchasedItems } = useCart();
  const [draft, setDraft] = useState<CheckoutDraft | null>(null);
  const [isReady, setIsReady] = useState(false);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethodCode | null>(null);
  const [paymentError, setPaymentError] = useState<string | undefined>();
  const [submitError, setSubmitError] = useState<string | undefined>();
  const [isProcessingPayment, setIsProcessingPayment] = useState(false);
  const paymentLockRef = useRef(false);
  const mountedRef = useRef(false);

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
    if (existingOrderId && getStoredOrderById(existingOrderId)) {
      finishOrder(existingOrderId, clearPurchasedItems, router);
      return;
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
    if (existingOrderId && getStoredOrderById(existingOrderId)) {
      finishOrder(existingOrderId, clearPurchasedItems, router);
      return;
    }

    if (!acquireDraftSubmissionLock(draft.draftId)) {
      const lockedOrderId = getOrderIdForDraft(draft.draftId);
      if (lockedOrderId && getStoredOrderById(lockedOrderId)) {
        finishOrder(lockedOrderId, clearPurchasedItems, router);
      }
      return;
    }

    setPaymentError(undefined);
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

      finishOrder(order.id, clearPurchasedItems, router);
    } catch (error) {
      releaseDraftSubmissionLock(draft.draftId);
      paymentLockRef.current = false;
      const message =
        error instanceof Error ? error.message : "We couldn't place your order. Please try again.";
      setSubmitError(message);
      setIsProcessingPayment(false);
    }
  }, [clearPurchasedItems, draft, isProcessingPayment, paymentMethod, router]);

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

  const submitLabel =
    paymentMethod === PAYMENT_METHOD_CODES.MPESA
      ? isProcessingPayment
        ? "Processing M-Pesa…"
        : "Pay with M-Pesa"
      : paymentMethod === PAYMENT_METHOD_CODES.COD
        ? isProcessingPayment
          ? "Placing order…"
          : "Place Order (COD)"
        : isProcessingPayment
          ? "Placing order…"
          : "Place Order";

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
        </fieldset>

        <CheckoutOrderSummary
          items={draft.items}
          totals={draft.totals}
          onSubmit={handleSubmit}
          isSubmitting={isProcessingPayment}
          submitDisabled={isProcessingPayment}
          submitLabel={submitLabel}
          submitHint={
            isProcessingPayment
              ? "Processing — please do not refresh or click again"
              : "Payment step — shipping already confirmed"
          }
          backHref="/checkout"
          backLabel="← Back to checkout"
        />
      </div>
    </div>
  );
}
