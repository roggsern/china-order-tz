import assert from "node:assert/strict";
import { test } from "node:test";
import { applyFreshNmbCheckoutSession } from "./apply-fresh-checkout-session";
import {
  clearNmbCheckoutContext,
  readNmbCheckoutContext,
  saveNmbCheckoutContext,
} from "./checkout-context";
import type { PaymentTransactionPayload } from "@/lib/api/customer-payment-orchestrator";

function fakeTransaction(
  overrides: Partial<PaymentTransactionPayload> = {},
): PaymentTransactionPayload {
  return {
    id: "txn-1",
    order_id: "order-1",
    provider: "nmb",
    provider_reference: "SESSION-FRESH",
    merchant_reference: "COTZ-PAY-1",
    currency: "TZS",
    amount: "1000.00",
    status: "processing",
    success_indicator: "indicator-fresh",
    ...overrides,
  };
}

test("applyFreshNmbCheckoutSession replaces stale gateway session context", () => {
  globalThis.window = {
    sessionStorage: (() => {
      const store = new Map<string, string>();
      return {
        getItem: (key: string) => store.get(key) ?? null,
        setItem: (key: string, value: string) => {
          store.set(key, value);
        },
        removeItem: (key: string) => {
          store.delete(key);
        },
      };
    })(),
  } as Window & typeof globalThis;

  saveNmbCheckoutContext({
    paymentId: "txn-1",
    paymentTransactionId: "txn-1",
    gatewaySessionId: "SESSION-STALE",
    successIndicator: "indicator-stale",
    orderId: "order-1",
  });

  const result = applyFreshNmbCheckoutSession(fakeTransaction(), "txn-1");

  assert.equal(result.sessionId, "SESSION-FRESH");
  assert.equal(result.successIndicator, "indicator-fresh");

  const context = readNmbCheckoutContext();
  assert.equal(context?.gatewaySessionId, "SESSION-FRESH");
  assert.equal(context?.successIndicator, "indicator-fresh");
  assert.notEqual(context?.gatewaySessionId, "SESSION-STALE");

  clearNmbCheckoutContext();
});

test("applyFreshNmbCheckoutSession rejects missing provider_reference", () => {
  globalThis.window = {
    sessionStorage: {
      getItem: () => null,
      setItem: () => undefined,
      removeItem: () => undefined,
    },
  } as Window & typeof globalThis;

  assert.throws(
    () =>
      applyFreshNmbCheckoutSession(
        fakeTransaction({ provider_reference: null }),
        "txn-1",
      ),
    /checkout session id/i,
  );
});
