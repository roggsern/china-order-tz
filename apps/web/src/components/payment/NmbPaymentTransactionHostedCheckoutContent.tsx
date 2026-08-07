"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  fetchPaymentTransaction,
  PaymentOrchestratorApiError,
  type PaymentTransactionPayload,
} from "@/lib/api/customer-payment-orchestrator";
import {
  patchNmbCheckoutContext,
  readNmbCheckoutContext,
  saveNmbCheckoutContext,
} from "@/lib/nmb/checkout-context";
import { getNmbReturnUrl } from "@/lib/nmb/config";
import {
  describeHostedCheckoutError,
  launchMpgsHostedCheckout,
} from "@/lib/nmb/hosted-checkout";
import { prepareNmbHostedCheckoutLaunch } from "@/lib/nmb/orchestrator-checkout";
import { buildPaymentReturnPath } from "@/lib/nmb/payment-return";

type NmbPaymentTransactionHostedCheckoutContentProps = {
  paymentTransactionId: string;
  sessionId?: string;
  successIndicator?: string | null;
};

type CheckoutPhase = "loading" | "redirecting" | "error";

/** Customer order details route uses order_number (COTZ-YYYYMMDD-######), never UUID. */
function looksLikeCustomerOrderNumber(value: string | null | undefined): value is string {
  return typeof value === "string" && /^COTZ-\d{8}-\d{6}$/.test(value.trim());
}

function orderNumberFromCheckoutContext(): string | null {
  const context = readNmbCheckoutContext();
  for (const candidate of [context?.localOrderId, context?.orderId]) {
    if (looksLikeCustomerOrderNumber(candidate)) {
      return candidate.trim();
    }
  }
  return null;
}

function orderNumberFromTransaction(transaction: PaymentTransactionPayload): string | null {
  const fromOrder = transaction.order?.order_number?.trim();
  return looksLikeCustomerOrderNumber(fromOrder) ? fromOrder : null;
}

export function NmbPaymentTransactionHostedCheckoutContent({
  paymentTransactionId,
  sessionId: sessionIdProp,
  successIndicator: successIndicatorProp,
}: NmbPaymentTransactionHostedCheckoutContentProps) {
  const router = useRouter();
  const launchedRef = useRef(false);
  const [phase, setPhase] = useState<CheckoutPhase>("loading");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [orderNumber, setOrderNumber] = useState<string | null>(() => orderNumberFromCheckoutContext());

  const resolveOrderNumber = useCallback(async () => {
    const fromContext = orderNumberFromCheckoutContext();
    if (fromContext) {
      setOrderNumber(fromContext);
      return fromContext;
    }

    const token = getCustomerApiToken();
    if (!token) {
      return null;
    }

    try {
      const transaction = await fetchPaymentTransaction(paymentTransactionId, token);
      const fromTransaction = orderNumberFromTransaction(transaction);
      if (fromTransaction) {
        setOrderNumber(fromTransaction);
        return fromTransaction;
      }
    } catch {
      // Keep View order fallback to /orders when lookup fails.
    }

    return null;
  }, [paymentTransactionId]);

  const redirectToReturn = useCallback(
    (resultIndicator?: string) => {
      const context = readNmbCheckoutContext();
      const paymentTxnId =
        context?.paymentTransactionId ?? context?.paymentId ?? paymentTransactionId;

      const path = buildPaymentReturnPath({
        resultIndicator,
        paymentTransactionId: paymentTxnId,
        orderId: context?.orderId,
        localOrderId: context?.localOrderId,
        merchantReference: context?.merchantReference,
        successIndicator: context?.successIndicator ?? successIndicatorProp,
      });

      // Keep absolute origin from configured return URL when available.
      try {
        const configured = new URL(getNmbReturnUrl());
        router.replace(`${path}${configured.hash || ""}`);
      } catch {
        router.replace(path);
      }
    },
    [paymentTransactionId, router, successIndicatorProp],
  );

  const launchWithSession = useCallback(
    async (sessionId: string, successIndicator: string | null) => {
      const existing = readNmbCheckoutContext();
      saveNmbCheckoutContext({
        paymentId: paymentTransactionId,
        paymentTransactionId,
        gatewaySessionId: sessionId,
        successIndicator,
        orderId: existing?.orderId ?? null,
        localOrderId: existing?.localOrderId ?? null,
        merchantReference: existing?.merchantReference ?? null,
      });

      setPhase("redirecting");

      await launchMpgsHostedCheckout({
        sessionId,
        callbacks: {
          onComplete: (resultIndicator) => {
            patchNmbCheckoutContext({
              resultIndicator,
              paymentTransactionId,
              paymentId: paymentTransactionId,
              successIndicator,
            });
            redirectToReturn(resultIndicator);
          },
          onCancel: () => {
            redirectToReturn();
          },
          onError: (error) => {
            setPhase("error");
            setErrorMessage(describeHostedCheckoutError(error));
            void resolveOrderNumber();
          },
          onTimeout: () => {
            setPhase("error");
            setErrorMessage("The payment session timed out. Please try again.");
            void resolveOrderNumber();
          },
        },
      });
    },
    [paymentTransactionId, redirectToReturn, resolveOrderNumber],
  );

  const startHostedCheckout = useCallback(async () => {
    const token = getCustomerApiToken();
    if (!token) {
      throw new PaymentOrchestratorApiError("Please sign in to continue with payment.", 401);
    }

    let sessionId = sessionIdProp?.trim() || readNmbCheckoutContext()?.gatewaySessionId?.trim();
    let successIndicator =
      successIndicatorProp ?? readNmbCheckoutContext()?.successIndicator ?? null;

    if (!sessionId) {
      const transaction = await fetchPaymentTransaction(paymentTransactionId, token);

      if (transaction.provider !== "nmb") {
        throw new PaymentOrchestratorApiError("This payment is not an NMB transaction.");
      }

      const fromTransaction = orderNumberFromTransaction(transaction);
      if (fromTransaction) {
        setOrderNumber(fromTransaction);
      }

      sessionId = transaction.provider_reference?.trim() || undefined;
      successIndicator = transaction.success_indicator ?? successIndicator;

      if (!sessionId) {
        throw new PaymentOrchestratorApiError("NMB did not return a checkout session id.");
      }

      prepareNmbHostedCheckoutLaunch(transaction);
    } else {
      const existing = readNmbCheckoutContext();
      saveNmbCheckoutContext({
        ...(existing ?? { paymentId: paymentTransactionId }),
        paymentId: paymentTransactionId,
        paymentTransactionId,
        gatewaySessionId: sessionId,
        successIndicator,
        orderId: existing?.orderId ?? null,
        merchantReference: existing?.merchantReference ?? null,
        localOrderId: existing?.localOrderId ?? null,
      });
    }

    await launchWithSession(sessionId, successIndicator);
  }, [
    launchWithSession,
    paymentTransactionId,
    sessionIdProp,
    successIndicatorProp,
  ]);

  useEffect(() => {
    if (launchedRef.current) {
      return;
    }

    // Mastercard Hosted Checkout cancel returns to this page with #__hc-action-cancel.
    // Do not auto-relaunch the gateway on remount.
    if (typeof window !== "undefined" && window.location.hash === "#__hc-action-cancel") {
      setPhase("error");
      setErrorMessage("Payment was cancelled. You can try again when ready.");
      void resolveOrderNumber();
      return;
    }

    launchedRef.current = true;

    void (async () => {
      try {
        await startHostedCheckout();
      } catch (error) {
        launchedRef.current = false;
        setPhase("error");
        setErrorMessage(
          error instanceof PaymentOrchestratorApiError
            ? error.message
            : error instanceof Error
              ? error.message
              : "Unable to start NMB Hosted Checkout.",
        );
        void resolveOrderNumber();
      }
    })();
  }, [resolveOrderNumber, startHostedCheckout]);

  const orderDetailsHref = orderNumber
    ? `/orders/${encodeURIComponent(orderNumber)}`
    : "/orders";

  if (phase === "error") {
    return (
      <div className="mx-auto max-w-lg rounded-2xl border border-red-200 bg-white p-8 shadow-sm">
        <h1 className="text-xl font-semibold text-zinc-900">NMB payment unavailable</h1>
        <p className="mt-3 text-sm leading-6 text-zinc-600">{errorMessage}</p>
        <div className="mt-6 flex flex-wrap gap-3">
          <Link
            href={orderDetailsHref}
            className="rounded-full border border-zinc-300 px-5 py-2.5 text-sm font-medium text-zinc-700"
          >
            View order
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-lg rounded-2xl border border-zinc-200 bg-white p-8 text-center shadow-sm">
      <div className="mx-auto mb-4 h-10 w-10 animate-spin rounded-full border-2 border-zinc-300 border-t-zinc-900" />
      <h1 className="text-xl font-semibold text-zinc-900">
        {phase === "redirecting" ? "Opening secure payment page" : "Preparing NMB checkout"}
      </h1>
      <p className="mt-3 text-sm leading-6 text-zinc-600">
        {phase === "redirecting"
          ? "You will be redirected to the NMB Hosted payment page shortly."
          : "We are loading your NMB payment session. Do not close this window."}
      </p>
    </div>
  );
}
