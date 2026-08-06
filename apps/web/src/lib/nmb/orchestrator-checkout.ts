import type { PaymentTransactionPayload } from "@/lib/api/customer-payment-orchestrator";
import { saveNmbCheckoutContext, setNmbPendingPaymentId } from "@/lib/nmb/checkout-context";

export type PaymentStartNavigation =
  | { type: "external"; url: string }
  | { type: "hosted"; path: string }
  | { type: "status"; path: string };

type NavigateRouter = {
  push: (path: string) => void;
  replace: (path: string) => void;
};

export function isNmbWebsiteHostedCheckout(transaction: PaymentTransactionPayload): boolean {
  return (
    transaction.provider === "nmb" &&
    !transaction.checkout_url?.trim() &&
    Boolean(transaction.provider_reference?.trim())
  );
}

export function prepareNmbHostedCheckoutLaunch(
  transaction: PaymentTransactionPayload,
  extras?: { localOrderId?: string | null },
): void {
  saveNmbCheckoutContext({
    paymentId: transaction.id,
    paymentTransactionId: transaction.id,
    gatewaySessionId: transaction.provider_reference ?? null,
    successIndicator: transaction.success_indicator ?? null,
    orderId: transaction.order_id,
    merchantReference: transaction.merchant_reference ?? null,
    localOrderId: extras?.localOrderId ?? null,
  });
  setNmbPendingPaymentId(transaction.id);
}

export function buildNmbHostedCheckoutLauncherPath(paymentTransactionId: string): string {
  return `/payments/${encodeURIComponent(paymentTransactionId)}/nmb`;
}

export function resolvePaymentStartNavigation(
  transaction: PaymentTransactionPayload,
  extras?: { localOrderId?: string | null },
): PaymentStartNavigation {
  const checkoutUrl = transaction.checkout_url?.trim();
  if (checkoutUrl) {
    return { type: "external", url: checkoutUrl };
  }

  if (isNmbWebsiteHostedCheckout(transaction)) {
    prepareNmbHostedCheckoutLaunch(transaction, extras);
    return {
      type: "hosted",
      path: buildNmbHostedCheckoutLauncherPath(transaction.id),
    };
  }

  return {
    type: "status",
    path: `/payments/${encodeURIComponent(transaction.id)}`,
  };
}

export function navigateAfterPaymentStart(
  router: NavigateRouter,
  transaction: PaymentTransactionPayload,
  options?: { localOrderId?: string | null; replace?: boolean },
): void {
  const navigation = resolvePaymentStartNavigation(transaction, {
    localOrderId: options?.localOrderId,
  });
  const navigate = options?.replace === false ? router.push : router.replace;

  if (navigation.type === "external") {
    window.location.assign(navigation.url);
    return;
  }

  navigate(navigation.path);
}
