import assert from "node:assert/strict";
import { test } from "node:test";
import type { PaymentTransactionPayload } from "@/lib/api/customer-payment-orchestrator";
import {
  buildNmbHostedCheckoutLauncherPath,
  isNmbWebsiteHostedCheckout,
  resolvePaymentStartNavigation,
} from "./orchestrator-checkout";

function makeTransaction(
  overrides: Partial<PaymentTransactionPayload> = {},
): PaymentTransactionPayload {
  return {
    id: "txn-123",
    order_id: "order-456",
    provider: "nmb",
    merchant_reference: "COTZ-PAY-000001",
    currency: "TZS",
    amount: "25000.00",
    status: "processing",
    provider_reference: "SESSION0001234567890123456789012345",
    success_indicator: "success-indicator-abc",
    checkout_url: null,
    ...overrides,
  };
}

test("isNmbWebsiteHostedCheckout is true for MPGS WEBSITE session without checkout_url", () => {
  assert.equal(isNmbWebsiteHostedCheckout(makeTransaction()), true);
});

test("isNmbWebsiteHostedCheckout is false when checkout_url exists", () => {
  assert.equal(
    isNmbWebsiteHostedCheckout(
      makeTransaction({ checkout_url: "https://checkout.nmb.test/pay/1" }),
    ),
    false,
  );
});

test("resolvePaymentStartNavigation prefers external checkout_url", () => {
  const navigation = resolvePaymentStartNavigation(
    makeTransaction({ checkout_url: "https://checkout.nmb.test/pay/1" }),
  );

  assert.deepEqual(navigation, {
    type: "external",
    url: "https://checkout.nmb.test/pay/1",
  });
});

test("resolvePaymentStartNavigation routes MPGS WEBSITE sessions to hosted launcher", () => {
  const navigation = resolvePaymentStartNavigation(makeTransaction());

  assert.deepEqual(navigation, {
    type: "hosted",
    path: "/payments/txn-123/nmb",
  });
});

test("resolvePaymentStartNavigation falls back to payment status page", () => {
  const navigation = resolvePaymentStartNavigation(
    makeTransaction({ provider_reference: null }),
  );

  assert.deepEqual(navigation, {
    type: "status",
    path: "/payments/txn-123",
  });
});

test("buildNmbHostedCheckoutLauncherPath encodes transaction id", () => {
  assert.equal(
    buildNmbHostedCheckoutLauncherPath("txn/with/slash"),
    "/payments/txn%2Fwith%2Fslash/nmb",
  );
});
