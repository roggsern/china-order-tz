"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { getCustomerApiToken } from "@/lib/api/customer-auth";
import {
  clearNmbCheckoutContext,
  patchNmbCheckoutContext,
  readNmbCheckoutContext,
  saveNmbCheckoutContext,
} from "@/lib/nmb/checkout-context";
import { getNmbReturnUrl } from "@/lib/nmb/config";
import {
  describeHostedCheckoutError,
  launchMpgsHostedCheckout,
} from "@/lib/nmb/hosted-checkout";
import {
  initiateNmbPaymentSession,
  NmbPaymentSessionError,
} from "@/lib/nmb/payment-session-api";

type NmbHostedCheckoutContentProps = {
  paymentId: string;
};

type CheckoutPhase = "loading" | "redirecting" | "error";

export function NmbHostedCheckoutContent({ paymentId }: NmbHostedCheckoutContentProps) {
  const router = useRouter();
  const launchedRef = useRef(false);
  const [phase, setPhase] = useState<CheckoutPhase>("loading");
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  const redirectToReturn = useCallback(
    (resultIndicator?: string) => {
      const returnUrl = new URL(getNmbReturnUrl());

      if (resultIndicator) {
        returnUrl.searchParams.set("resultIndicator", resultIndicator);
      }

      const context = readNmbCheckoutContext();
      if (context?.orderId) {
        returnUrl.searchParams.set("orderId", context.orderId);
      }
      if (context?.localOrderId) {
        returnUrl.searchParams.set("localOrderId", context.localOrderId);
      }

      router.replace(`${returnUrl.pathname}${returnUrl.search}`);
    },
    [router],
  );

  useEffect(() => {
    if (launchedRef.current) {
      return;
    }

    launchedRef.current = true;

    async function startHostedCheckout() {
      try {
        if (!getCustomerApiToken()) {
          throw new NmbPaymentSessionError("Please sign in to continue with payment.");
        }

        saveNmbCheckoutContext({
          ...(readNmbCheckoutContext() ?? {}),
          paymentId,
        });

        const session = await initiateNmbPaymentSession(paymentId);
        const gatewaySessionId = session.data.gateway_session_id;

        if (!gatewaySessionId) {
          throw new NmbPaymentSessionError("NMB did not return a checkout session id.");
        }

        patchNmbCheckoutContext({
          paymentId: session.data.payment_id,
          orderId: session.data.order_id,
          gatewaySessionId,
        });

        setPhase("redirecting");

        await launchMpgsHostedCheckout({
          sessionId: gatewaySessionId,
          callbacks: {
            onComplete: (resultIndicator) => {
              patchNmbCheckoutContext({
                resultIndicator,
              });
              redirectToReturn(resultIndicator);
            },
            onCancel: () => {
              setPhase("error");
              setErrorMessage("Payment was cancelled. You can try again when ready.");
            },
            onError: (error) => {
              setPhase("error");
              setErrorMessage(describeHostedCheckoutError(error));
            },
            onTimeout: () => {
              setPhase("error");
              setErrorMessage("The payment session timed out. Please try again.");
            },
          },
        });
      } catch (error) {
        launchedRef.current = false;
        setPhase("error");
        setErrorMessage(
          error instanceof NmbPaymentSessionError
            ? error.message
            : error instanceof Error
              ? error.message
              : "Unable to start NMB Hosted Checkout.",
        );
      }
    }

    void startHostedCheckout();
  }, [paymentId, redirectToReturn]);

  if (phase === "error") {
    return (
      <div className="mx-auto max-w-lg rounded-2xl border border-red-200 bg-white p-8 shadow-sm">
        <h1 className="text-xl font-semibold text-zinc-900">NMB payment unavailable</h1>
        <p className="mt-3 text-sm leading-6 text-zinc-600">{errorMessage}</p>
        <div className="mt-6 flex flex-wrap gap-3">
          <button
            type="button"
            onClick={() => {
              launchedRef.current = false;
              setPhase("loading");
              setErrorMessage(null);
              window.location.reload();
            }}
            className="rounded-full bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white"
          >
            Try again
          </button>
          <Link
            href="/checkout/payment"
            className="rounded-full border border-zinc-300 px-5 py-2.5 text-sm font-medium text-zinc-700"
          >
            Back to payment
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
          ? "You will be redirected to the NMB hosted payment page shortly."
          : "We are creating your payment session. Do not close this window."}
      </p>
    </div>
  );
}

export function clearNmbHostedCheckoutState(): void {
  clearNmbCheckoutContext();
}
