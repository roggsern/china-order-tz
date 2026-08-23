"use client";

import { useCallback, useEffect, useMemo, useRef, useState } from "react";
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
import { PAYMENT_METHOD_CODES, PAYMENT_STATUS } from "@/lib/types/payment";
import type { PaymentMethodCode } from "@/lib/types/payment";
import { paymentService } from "@/lib/payments/checkout-service";
import { navigateAfterPaymentStart } from "@/lib/nmb";
import {
  CustomerPaymentApiError,
  prepareOrderPayment,
  toBackendPaymentMethod,
} from "@/lib/api/customer-payments";
import {
  PaymentOrchestratorApiError,
  startPaymentTransaction,
} from "@/lib/api/customer-payment-orchestrator";
import {
  CheckoutPaymentMethodsApiError,
  fetchCheckoutPaymentMethods,
  prefetchCheckoutPaymentMethods,
} from "@/lib/api/checkout-payment-methods";
import {
  buildCheckoutPaymentOptions,
  resolveDefaultCheckoutPaymentCode,
  type CheckoutPaymentOption,
} from "@/lib/checkout/payment-availability";
import { getOrderById as getStoredOrderById } from "@/lib/payment/order-storage";
import { getPaymentTransaction, savePaymentTransaction } from "@/lib/payment/payment-session";
import { redirectToPaymentProcessing } from "@/lib/payment/stk-flow";
import { shouldRedirectToOrderSuccess, isAwaitingPaymentSelection } from "@/lib/order/placement";
import {
  isGatewayPaymentMethod,
  isOrchestratorPaymentMethod,
} from "@/lib/payment/payment-outcome";
import {
  resolveSnippeStartFailureMessage,
  validateSnippePhoneInput,
} from "@/lib/payment/snippe";
import { updateOrderById } from "@/lib/payment/order-storage";
import { CheckoutSection } from "./CheckoutSection";
import { CheckoutOrderSummary } from "./CheckoutOrderSummary";
import { CheckoutStepIndicator } from "./CheckoutStepIndicator";
import { CheckoutMobileStickyBar } from "./CheckoutMobileStickyBar";
import { SimplifiedPaymentMethodSelector } from "@/components/payment/SimplifiedPaymentMethodSelector";
import { SnippeMobileMoneyPhoneField } from "@/components/payment/SnippeMobileMoneyPhoneField";
import { CheckoutPageSkeleton } from "@/components/ui/PageSkeletons";
import { useStorefrontTracking } from "@/components/storefront/StorefrontTrackingProvider";

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

function submitLabelForMethod(
  method: PaymentMethodCode | null,
  isProcessing: boolean,
  hasFailedOrder: boolean,
): string {
  if (isProcessing) {
    if (method === PAYMENT_METHOD_CODES.NMB) return "Redirecting to secure checkout…";
    if (method === PAYMENT_METHOD_CODES.SNIPPE) return "Sending payment request…";
    return "Placing order…";
  }
  if (hasFailedOrder) {
    if (method === PAYMENT_METHOD_CODES.NMB) return "Retry payment";
    if (method === PAYMENT_METHOD_CODES.SNIPPE) return "Retry Mobile Money payment";
    return "Retry order payment";
  }
  if (method === PAYMENT_METHOD_CODES.NMB) return "Pay securely";
  if (method === PAYMENT_METHOD_CODES.SNIPPE) return "Pay with Mobile Money";
  if (method === PAYMENT_METHOD_CODES.COD) return "Place order (pay on delivery)";
  if (method === PAYMENT_METHOD_CODES.BANK_TRANSFER) return "Place order (bank transfer)";
  return "Continue to payment";
}

export function PaymentPageContent() {
  const router = useRouter();
  const { clearPurchasedItems } = useCart();
  const { trackPaymentStarted } = useStorefrontTracking();
  const paymentTrackedRef = useRef(false);
  const [draft, setDraft] = useState<CheckoutDraft | null>(null);
  const [isReady, setIsReady] = useState(false);
  const [submitError, setSubmitError] = useState<string | undefined>();
  const [failedOrder, setFailedOrder] = useState<Order | null>(null);
  const [isProcessingPayment, setIsProcessingPayment] = useState(false);
  const paymentLockRef = useRef(false);
  const mountedRef = useRef(false);
  const [paymentOptions, setPaymentOptions] = useState<CheckoutPaymentOption[]>([]);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethodCode | null>(null);
  const [methodsLoading, setMethodsLoading] = useState(true);
  const [methodsError, setMethodsError] = useState<string | undefined>();
  const [snippePhone, setSnippePhone] = useState("");
  const [snippePhoneError, setSnippePhoneError] = useState<string | undefined>();

  useEffect(() => {
    if (paymentTrackedRef.current) {
      return;
    }

    paymentTrackedRef.current = true;
    void trackPaymentStarted();
  }, [trackPaymentStarted]);

  useEffect(() => {
    prefetchCheckoutPaymentMethods();

    let cancelled = false;
    void (async () => {
      setMethodsLoading(true);
      setMethodsError(undefined);
      try {
        const availability = await fetchCheckoutPaymentMethods();
        if (cancelled) return;
        const options = buildCheckoutPaymentOptions(availability);
        setPaymentOptions(options);
        setPaymentMethod(resolveDefaultCheckoutPaymentCode(availability, options));
      } catch (error) {
        if (cancelled) return;
        setPaymentOptions([]);
        setPaymentMethod(null);
        setMethodsError(
          error instanceof CheckoutPaymentMethodsApiError
            ? error.message
            : "Unable to load payment methods.",
        );
      } finally {
        if (!cancelled) {
          setMethodsLoading(false);
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, []);

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
        const waitingForPayment =
          savedDraft.awaitingPayment === true || isAwaitingPaymentSelection(existing);

        if (!waitingForPayment && shouldRedirectToOrderSuccess(existing)) {
          finishOrder(existing, clearPurchasedItems, router);
          return;
        }

        if (
          !waitingForPayment &&
          existing.paymentStatus === PAYMENT_STATUS.PENDING
        ) {
          const transactionId =
            existing.paymentTransactionId ?? getPaymentTransaction(existing.id);

          if (transactionId && isOrchestratorPaymentMethod(existing.paymentMethod)) {
            clearCheckoutDraft();
            router.replace(`/payments/${encodeURIComponent(transactionId)}`);
            return;
          }

          if (transactionId && isGatewayPaymentMethod(existing.paymentMethod)) {
            clearCheckoutDraft();
            redirectToPaymentProcessing(router, existing.id, transactionId);
            return;
          }
        }

        if (existing.paymentStatus === PAYMENT_STATUS.FAILED) {
          lockCartForOrder(existing.id, clearPurchasedItems);
          setFailedOrder(existing);
        }
      }
    }

    setDraft(savedDraft);
    setSnippePhone(savedDraft.customer.phone?.trim() ?? "");
    setIsReady(true);
  }, [clearPurchasedItems, router]);

  useEffect(() => {
    if (paymentMethod !== PAYMENT_METHOD_CODES.SNIPPE) {
      setSnippePhoneError(undefined);
    }
  }, [paymentMethod]);

  const selectorOptions = useMemo(
    () =>
      paymentOptions.map((option) => ({
        code: option.code,
        label: option.label,
        description: option.description,
        icon: option.icon,
      })),
    [paymentOptions],
  );

  const handleSubmit = useCallback(async () => {
    if (paymentLockRef.current || isProcessingPayment || !draft || !paymentMethod) {
      return;
    }

    if (draft.items.length === 0) {
      setSubmitError("Your cart is empty. Add items before placing an order.");
      return;
    }

    const existingOrderId = getOrderIdForDraft(draft.draftId);
    if (existingOrderId) {
      const existing = getStoredOrderById(existingOrderId);
      if (
        existing &&
        !draft.awaitingPayment &&
        !isAwaitingPaymentSelection(existing) &&
        shouldRedirectToOrderSuccess(existing)
      ) {
        finishOrder(existing, clearPurchasedItems, router);
        return;
      }
    }

    if (!acquireDraftSubmissionLock(draft.draftId)) {
      const lockedOrderId = getOrderIdForDraft(draft.draftId);
      if (lockedOrderId) {
        const lockedOrder = getStoredOrderById(lockedOrderId);
        if (
          lockedOrder &&
          !draft.awaitingPayment &&
          !isAwaitingPaymentSelection(lockedOrder) &&
          shouldRedirectToOrderSuccess(lockedOrder)
        ) {
          finishOrder(lockedOrder, clearPurchasedItems, router);
        }
      }
      return;
    }

    if (paymentMethod === PAYMENT_METHOD_CODES.SNIPPE) {
      const phoneValidationError = validateSnippePhoneInput(snippePhone);
      if (phoneValidationError) {
        setSnippePhoneError(phoneValidationError);
        releaseDraftSubmissionLock(draft.draftId);
        paymentLockRef.current = false;
        setIsProcessingPayment(false);
        return;
      }
    }

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

      const backendOrderId = draft.backendOrder?.id ?? order.id;
      const backendMethod = toBackendPaymentMethod(paymentMethod);

      if (paymentMethod === PAYMENT_METHOD_CODES.NMB) {
        const transaction = await startPaymentTransaction(
          backendOrderId,
          backendMethod ?? undefined,
        );
        clearCheckoutDraft();
        navigateAfterPaymentStart(router, transaction, {
          localOrderId: order.id,
          replace: false,
        });
        return;
      }

      if (paymentMethod === PAYMENT_METHOD_CODES.SNIPPE) {
        const transaction = await startPaymentTransaction(backendOrderId, {
          provider: backendMethod ?? PAYMENT_METHOD_CODES.SNIPPE,
          phoneNumber: snippePhone.trim(),
        });

        if (transaction.status === "failed") {
          throw new PaymentOrchestratorApiError(resolveSnippeStartFailureMessage(transaction));
        }

        savePaymentTransaction(order.id, transaction.id);
        updateOrderById(order.id, (existing) => ({
          ...existing,
          paymentTransactionId: transaction.id,
        }));
        clearCheckoutDraft();
        navigateAfterPaymentStart(router, transaction, {
          localOrderId: order.id,
          replace: false,
        });
        return;
      }

      if (isGatewayPaymentMethod(paymentMethod)) {
        if (backendMethod) {
          try {
            await prepareOrderPayment(backendOrderId, backendMethod);
          } catch {
            // Local STK/Selcom can proceed even if Laravel payment row is unavailable.
          }
        }

        const { transactionId } = await paymentService.beginStkPaymentProcessing(order);
        clearCheckoutDraft();
        redirectToPaymentProcessing(router, order.id, transactionId);
        return;
      }

      if (paymentMethod === PAYMENT_METHOD_CODES.BANK_TRANSFER && backendMethod) {
        await prepareOrderPayment(backendOrderId, backendMethod);
      }

      if (paymentMethod === PAYMENT_METHOD_CODES.COD && backendMethod) {
        try {
          await prepareOrderPayment(backendOrderId, backendMethod);
        } catch {
          // COD can still finalize locally as payable on delivery.
        }
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
        error instanceof CustomerPaymentApiError ||
        error instanceof PaymentOrchestratorApiError ||
        error instanceof CheckoutPaymentMethodsApiError
          ? error.message
          : error instanceof Error
            ? error.message
            : "We couldn't place your order. Please try again.";
      setSubmitError(message);
      setIsProcessingPayment(false);
    }
  }, [clearPurchasedItems, draft, isProcessingPayment, paymentMethod, router, snippePhone]);

  if (!isReady || !draft) {
    return <CheckoutPageSkeleton />;
  }

  const submitLabel = submitLabelForMethod(
    paymentMethod,
    isProcessingPayment,
    Boolean(failedOrder),
  );
  const submitDisabled =
    isProcessingPayment || methodsLoading || !paymentMethod || paymentOptions.length === 0;

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
            Choose an available payment method and complete your order.
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

      {failedOrder ? (
        <div
          role="alert"
          className="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-4 sm:px-5"
        >
          <p className="text-sm font-semibold text-red-800">Previous payment attempt failed</p>
          <p className="mt-1 text-sm text-red-700">
            Order <span className="font-mono font-semibold">[{failedOrder.orderNumber}]</span> was
            created but payment did not go through. Your cart is locked — choose a method below to
            try again.
          </p>
        </div>
      ) : null}

      <div className="mt-8 grid gap-8 pb-28 lg:grid-cols-[minmax(0,1fr)_400px] lg:items-start lg:pb-0">
        <fieldset disabled={isProcessingPayment} className="space-y-6 border-0 p-0">
          <CheckoutSection
            title="Payment method"
            description="Only methods enabled for this store are shown."
          >
            {methodsLoading ? (
              <p className="text-sm text-zinc-500">Loading available payment methods…</p>
            ) : (
              <SimplifiedPaymentMethodSelector
                value={paymentMethod}
                onChange={setPaymentMethod}
                options={selectorOptions}
                disabled={isProcessingPayment}
                error={methodsError}
              />
            )}
          </CheckoutSection>

          {paymentMethod === PAYMENT_METHOD_CODES.SNIPPE ? (
            <CheckoutSection
              title="Mobile Money number"
              description="We'll send the payment request to this number."
            >
              <SnippeMobileMoneyPhoneField
                value={snippePhone}
                onChange={(value) => {
                  setSnippePhone(value);
                  if (snippePhoneError) {
                    setSnippePhoneError(undefined);
                  }
                }}
                disabled={isProcessingPayment}
                error={snippePhoneError}
              />
            </CheckoutSection>
          ) : null}

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
          shippingChoice={draft.shippingChoice ?? null}
          shippingMethod={draft.shippingMethod ?? null}
          onSubmit={handleSubmit}
          isSubmitting={isProcessingPayment}
          submitDisabled={submitDisabled}
          submitLabel={submitLabel}
          submitHint={
            isProcessingPayment
              ? "Processing your payment…"
              : failedOrder
                ? "Retry payment — your order is already saved"
                : "Review products, shipping, and total — then continue"
          }
          backHref="/checkout"
          backLabel="← Back to checkout"
        />
      </div>

      <CheckoutMobileStickyBar
        totals={draft.totals}
        onSubmit={handleSubmit}
        isSubmitting={isProcessingPayment}
        submitDisabled={submitDisabled}
        submitLabel={submitLabel}
        itemCount={draft.items.length}
      />
    </div>
  );
}
