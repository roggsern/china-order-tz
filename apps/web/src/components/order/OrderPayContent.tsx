"use client";

import { useCallback, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import { fetchCustomerOrder } from "@/lib/api/customer-orders";
import {
  CheckoutPaymentMethodsApiError,
  fetchCheckoutPaymentMethods,
} from "@/lib/api/checkout-payment-methods";
import {
  PaymentOrchestratorApiError,
  refreshPaymentTransaction,
  startPaymentTransaction,
  type PaymentTransactionPayload,
} from "@/lib/api/customer-payment-orchestrator";
import {
  CustomerPaymentApiError,
  prepareOrderPayment,
  toBackendPaymentMethod,
} from "@/lib/api/customer-payments";
import {
  buildCheckoutPaymentOptions,
  type CheckoutPaymentOption,
} from "@/lib/checkout/payment-availability";
import { isCustomerOrderPayable } from "@/lib/order/is-order-payable";
import {
  isPaymentInProgressError,
  paymentInProgressCustomerMessage,
  recoveryFromStartError,
  resolvePayNowView,
  resolveRefreshedTransactionView,
  type ActivePaymentTransactionRef,
  type PayNowView,
} from "@/lib/order/pay-now-recovery";
import { navigateAfterPaymentStart } from "@/lib/nmb/orchestrator-checkout";
import { formatPrice } from "@/lib/catalog/utils";
import { PAYMENT_METHOD_CODES } from "@/lib/types/payment";
import type { PaymentMethodCode } from "@/lib/types/payment";
import type { Order } from "@/lib/types/order";
import { validateSnippePhoneInput } from "@/lib/payment/snippe";
import { AuthInvitationCard } from "@/components/auth/AuthInvitationCard";
import { SimplifiedPaymentMethodSelector } from "@/components/payment/SimplifiedPaymentMethodSelector";
import { SnippeMobileMoneyPhoneField } from "@/components/payment/SnippeMobileMoneyPhoneField";

function providerLabel(provider: string | null | undefined): string {
  if (provider === "snippe") return "Mobile Money";
  if (provider === "nmb") return "NMB";
  if (provider === "cash") return "Pay at Office";
  return provider?.trim() || "payment";
}

function applyRefreshedTransaction(
  transaction: PaymentTransactionPayload,
): PayNowView {
  const view = resolveRefreshedTransactionView(transaction.status);
  if (view === "paid") {
    return { kind: "paid" };
  }
  if (view === "recovery") {
    return {
      kind: "recovery",
      transaction: {
        id: transaction.id,
        status: transaction.status,
        provider: transaction.provider,
      },
    };
  }
  return { kind: "selector" };
}

export function OrderPayContent({ orderNumber }: { orderNumber: string }) {
  const router = useRouter();
  const [needsAuth, setNeedsAuth] = useState(false);
  const [loading, setLoading] = useState(true);
  const [order, setOrder] = useState<Order | null>(null);
  const [view, setView] = useState<PayNowView | null>(null);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [paymentOptions, setPaymentOptions] = useState<CheckoutPaymentOption[]>([]);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethodCode | null>(null);
  const [methodsLoading, setMethodsLoading] = useState(true);
  const [methodsError, setMethodsError] = useState<string | undefined>();
  const [snippePhone, setSnippePhone] = useState("");
  const [snippePhoneError, setSnippePhoneError] = useState<string | undefined>();
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [statusNote, setStatusNote] = useState<string | null>(null);

  const detailsHref = `/orders/${encodeURIComponent(orderNumber)}`;

  const loadPage = useCallback(async () => {
    const token = getCustomerApiToken();
    if (!token) {
      setNeedsAuth(true);
      setLoading(false);
      return;
    }

    setLoading(true);
    setLoadError(null);
    setSubmitError(null);
    setStatusNote(null);

    try {
      const loadedOrder = await fetchCustomerOrder(orderNumber, token);
      setOrder(loadedOrder);

      const canPay = isCustomerOrderPayable({
        canPay: loadedOrder.canPay,
        status: loadedOrder.status,
        paymentStatus: loadedOrder.paymentStatus,
        paidAt: loadedOrder.paymentPaidAt,
      });

      let nextView = resolvePayNowView({
        canPay,
        orderStatus: loadedOrder.status,
        paymentStatus: loadedOrder.paymentStatus,
        activeTransaction: loadedOrder.activePaymentTransaction,
      });

      if (nextView.kind === "recovery") {
        const refreshed = await refreshPaymentTransaction(nextView.transaction.id, token);
        nextView = applyRefreshedTransaction(refreshed);
        if (nextView.kind === "paid") {
          setOrder((current) =>
            current
              ? { ...current, paymentStatus: "paid", canPay: false }
              : current,
          );
        }
      }

      if (nextView.kind === "selector") {
        try {
          const availability = await fetchCheckoutPaymentMethods(token);
          setPaymentOptions(buildCheckoutPaymentOptions(availability));
          setMethodsError(undefined);
        } catch (error) {
          if (error instanceof CheckoutPaymentMethodsApiError && error.statusCode === 401) {
            setNeedsAuth(true);
            return;
          }
          setPaymentOptions([]);
          setMethodsError(
            error instanceof CheckoutPaymentMethodsApiError
              ? error.message
              : "Unable to load payment methods.",
          );
        }
      }

      setView(nextView);
    } catch (error) {
      if (error instanceof PaymentOrchestratorApiError && error.statusCode === 401) {
        setNeedsAuth(true);
        return;
      }
      if (error instanceof CheckoutPaymentMethodsApiError && error.statusCode === 401) {
        setNeedsAuth(true);
        return;
      }
      setLoadError(error instanceof Error ? error.message : "Unable to load payment options.");
    } finally {
      setLoading(false);
      setMethodsLoading(false);
    }
  }, [orderNumber]);

  useEffect(() => {
    void loadPage();
  }, [loadPage]);

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

  const continuePayment = useCallback(
    async (transaction: ActivePaymentTransactionRef) => {
      const token = getCustomerApiToken();
      if (!token) {
        setNeedsAuth(true);
        return;
      }

      setBusy(true);
      setSubmitError(null);
      try {
        const refreshed = await refreshPaymentTransaction(transaction.id, token);
        const next = applyRefreshedTransaction(refreshed);
        if (next.kind === "paid") {
          setView(next);
          return;
        }
        if (next.kind === "selector") {
          setView(next);
          setStatusNote("The previous payment request is no longer active. Choose a payment method.");
          return;
        }
        navigateAfterPaymentStart(router, refreshed);
      } catch (error) {
        setSubmitError(error instanceof Error ? error.message : "Unable to continue this payment.");
      } finally {
        setBusy(false);
      }
    },
    [router],
  );

  const checkPaymentStatus = useCallback(
    async (transaction: ActivePaymentTransactionRef) => {
      const token = getCustomerApiToken();
      if (!token) {
        setNeedsAuth(true);
        return;
      }

      setBusy(true);
      setSubmitError(null);
      try {
        const refreshed = await refreshPaymentTransaction(transaction.id, token);
        const next = applyRefreshedTransaction(refreshed);
        setView(next);
        if (next.kind === "recovery") {
          setStatusNote("Your previous payment request is still pending.");
        } else if (next.kind === "selector") {
          setStatusNote("The previous payment request ended. You can choose another payment method.");
        }
      } catch (error) {
        setSubmitError(error instanceof Error ? error.message : "Unable to check payment status.");
      } finally {
        setBusy(false);
      }
    },
    [],
  );

  const handleSubmit = useCallback(async () => {
    if (!order || !paymentMethod || busy) {
      return;
    }

    if (paymentMethod === PAYMENT_METHOD_CODES.SNIPPE) {
      const phoneError = validateSnippePhoneInput(snippePhone);
      if (phoneError) {
        setSnippePhoneError(phoneError);
        return;
      }
    }

    const token = getCustomerApiToken();
    if (!token) {
      setNeedsAuth(true);
      return;
    }

    setBusy(true);
    setSubmitError(null);
    setSnippePhoneError(undefined);

    try {
      const backendMethod = toBackendPaymentMethod(paymentMethod);

      if (
        paymentMethod === PAYMENT_METHOD_CODES.COD ||
        paymentMethod === PAYMENT_METHOD_CODES.BANK_TRANSFER
      ) {
        if (!backendMethod) {
          throw new Error("This payment method is not available right now.");
        }
        await prepareOrderPayment(order.id, backendMethod, token);
        router.replace(detailsHref);
        return;
      }

      const transaction = await startPaymentTransaction(
        order.id,
        {
          provider: backendMethod ?? paymentMethod,
          phoneNumber:
            paymentMethod === PAYMENT_METHOD_CODES.SNIPPE ? snippePhone.trim() : undefined,
        },
        token,
      );

      if (transaction.status === "successful") {
        setView({ kind: "paid" });
        return;
      }

      navigateAfterPaymentStart(router, transaction);
    } catch (error) {
      if (error instanceof PaymentOrchestratorApiError && error.statusCode === 401) {
        setNeedsAuth(true);
        return;
      }

      if (isPaymentInProgressError(error) && error instanceof PaymentOrchestratorApiError) {
        const recovered = recoveryFromStartError(error);
        setView(
          recovered
            ? { kind: "recovery", transaction: recovered }
            : {
                kind: "recovery",
                transaction: order.activePaymentTransaction ?? {
                  id: error.paymentTransactionId ?? "",
                  status: error.paymentTransactionStatus ?? "processing",
                  provider: error.provider ?? null,
                },
              },
        );
        setSubmitError(null);
        setStatusNote(paymentInProgressCustomerMessage());
        return;
      }

      setSubmitError(
        error instanceof CustomerPaymentApiError || error instanceof PaymentOrchestratorApiError
          ? error.message
          : error instanceof Error
            ? error.message
            : "Unable to start payment.",
      );
    } finally {
      setBusy(false);
    }
  }, [busy, detailsHref, order, paymentMethod, router, snippePhone]);

  if (needsAuth) {
    return (
      <div className="mx-auto max-w-lg px-4 py-12">
        <AuthInvitationCard
          context="checkout"
          returnUrl={`/orders/${encodeURIComponent(orderNumber)}/pay`}
        />
      </div>
    );
  }

  if (loading || !view) {
    return (
      <div className="mx-auto max-w-lg px-4 py-16 text-center text-sm text-zinc-500">
        Loading payment options…
      </div>
    );
  }

  if (loadError) {
    return (
      <div className="mx-auto max-w-lg px-4 py-12">
        <div className="rounded-2xl border border-red-200 bg-red-50 p-5" role="alert">
          <p className="text-sm font-semibold text-red-900">Could not open payment</p>
          <p className="mt-1 text-sm text-red-800">{loadError}</p>
        </div>
        <Link href={detailsHref} className="mt-4 inline-block text-sm font-semibold text-[#8b6914]">
          Back to order
        </Link>
      </div>
    );
  }

  if (view.kind === "paid") {
    return (
      <div className="mx-auto max-w-lg px-4 py-12">
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
          <p className="text-sm font-semibold text-emerald-900">This order is already paid</p>
          <p className="mt-1 text-sm text-emerald-800">
            No further payment is needed. You can view the order details anytime.
          </p>
        </div>
        <Link
          href={detailsHref}
          className="mt-4 inline-flex min-h-10 items-center justify-center rounded-xl bg-zinc-900 px-4 text-sm font-bold text-white"
        >
          View order
        </Link>
      </div>
    );
  }

  if (view.kind === "not_payable") {
    return (
      <div className="mx-auto max-w-lg px-4 py-12">
        <div className="rounded-2xl border border-zinc-200 bg-zinc-50 p-5">
          <p className="text-sm font-semibold text-zinc-900">This order cannot be paid now</p>
          <p className="mt-1 text-sm text-zinc-600">
            {view.reason === "cancelled"
              ? "The order is cancelled or refunded, so payment is no longer available."
              : "Payment is not available for this order."}
          </p>
        </div>
        <Link href={detailsHref} className="mt-4 inline-block text-sm font-semibold text-[#8b6914]">
          Back to order
        </Link>
      </div>
    );
  }

  if (view.kind === "recovery") {
    return (
      <div className="mx-auto max-w-lg px-4 py-12">
        <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">Pay now</p>
        <h1 className="mt-1.5 text-3xl font-bold tracking-tight text-zinc-900">
          Payment still pending
        </h1>
        {order ? (
          <p className="mt-2 text-sm text-zinc-500">
            Order <span className="font-mono font-semibold text-zinc-800">{order.orderNumber}</span>
            {" · "}
            {formatPrice(order.grandTotal)}
          </p>
        ) : null}

        <div className="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
          <p className="text-sm font-semibold text-amber-950">
            {paymentInProgressCustomerMessage()}
          </p>
          <p className="mt-2 text-sm text-amber-900">
            Your previous {providerLabel(view.transaction.provider)} request is still pending. We
            will not start another live payment until this one is finished or closed.
          </p>
          {statusNote ? <p className="mt-2 text-sm text-amber-900">{statusNote}</p> : null}
        </div>

        {submitError ? (
          <p role="alert" className="mt-4 text-sm text-red-700">
            {submitError}
          </p>
        ) : null}

        <div className="mt-6 flex flex-col gap-2">
          <button
            type="button"
            disabled={busy}
            onClick={() => void checkPaymentStatus(view.transaction)}
            className="inline-flex min-h-11 items-center justify-center rounded-xl bg-zinc-900 px-4 text-sm font-bold text-white disabled:opacity-60"
          >
            {busy ? "Checking…" : "Check payment status"}
          </button>
          <button
            type="button"
            disabled={busy}
            onClick={() => void continuePayment(view.transaction)}
            className="inline-flex min-h-11 items-center justify-center rounded-xl border border-zinc-200 bg-white px-4 text-sm font-semibold text-zinc-800 disabled:opacity-60"
          >
            Continue payment
          </button>
          <Link
            href={detailsHref}
            className="inline-flex min-h-11 items-center justify-center text-sm font-semibold text-[#8b6914]"
          >
            Back to order
          </Link>
        </div>
      </div>
    );
  }

  const submitDisabled = busy || methodsLoading || !paymentMethod || paymentOptions.length === 0;

  return (
    <div className="mx-auto max-w-lg px-4 py-12">
      <p className="text-xs font-bold uppercase tracking-[0.16em] text-[#c9a227]">Pay now</p>
      <h1 className="mt-1.5 text-3xl font-bold tracking-tight text-zinc-900">
        Choose a payment method
      </h1>
      {order ? (
        <p className="mt-2 text-sm text-zinc-500">
          Order <span className="font-mono font-semibold text-zinc-800">{order.orderNumber}</span>
          {" · "}
          {formatPrice(order.grandTotal)}
        </p>
      ) : null}
      <p className="mt-2 text-sm text-zinc-500">
        Available methods come from the store. The default option is not applied until you choose
        one.
      </p>

      {statusNote ? (
        <p className="mt-4 rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700">
          {statusNote}
        </p>
      ) : null}

      <div className="mt-6">
        {methodsLoading ? (
          <p className="text-sm text-zinc-500">Loading available payment methods…</p>
        ) : (
          <SimplifiedPaymentMethodSelector
            value={paymentMethod}
            onChange={setPaymentMethod}
            options={selectorOptions}
            disabled={busy}
            error={methodsError}
          />
        )}
      </div>

      {paymentMethod === PAYMENT_METHOD_CODES.SNIPPE ? (
        <div className="mt-6">
          <SnippeMobileMoneyPhoneField
            value={snippePhone}
            onChange={(value) => {
              setSnippePhone(value);
              if (snippePhoneError) {
                setSnippePhoneError(undefined);
              }
            }}
            disabled={busy}
            error={snippePhoneError}
          />
        </div>
      ) : null}

      {submitError ? (
        <p
          role="alert"
          className="mt-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
          {submitError}
        </p>
      ) : null}

      <button
        type="button"
        disabled={submitDisabled}
        onClick={() => void handleSubmit()}
        className="mt-6 inline-flex min-h-11 w-full items-center justify-center rounded-xl bg-zinc-900 px-4 text-sm font-bold text-white disabled:opacity-60"
      >
        {busy ? "Starting payment…" : "Continue"}
      </button>
      <Link
        href={detailsHref}
        className="mt-3 inline-flex min-h-10 w-full items-center justify-center text-sm font-semibold text-[#8b6914]"
      >
        Back to order
      </Link>
    </div>
  );
}
