import assert from "node:assert/strict";
import { afterEach, test } from "node:test";
import {
  clearNmbCheckoutContext,
  peekNmbPendingPaymentId,
  readNmbCheckoutContext,
  saveNmbCheckoutContext,
} from "./checkout-context";

const memory: Record<string, Map<string, string>> = {
  session: new Map(),
  local: new Map(),
};

function installStorageMocks(): void {
  const makeStorage = (bucket: "session" | "local") =>
    ({
      getItem: (key: string) => memory[bucket].get(key) ?? null,
      setItem: (key: string, value: string) => {
        memory[bucket].set(key, value);
      },
      removeItem: (key: string) => {
        memory[bucket].delete(key);
      },
    }) as Storage;

  Object.defineProperty(globalThis, "window", {
    configurable: true,
    value: {
      sessionStorage: makeStorage("session"),
      localStorage: makeStorage("local"),
    },
  });
}

afterEach(() => {
  memory.session.clear();
  memory.local.clear();
  clearNmbCheckoutContext();
});

test("saveNmbCheckoutContext dual-writes session and durable local storage", () => {
  installStorageMocks();

  saveNmbCheckoutContext({
    paymentId: "txn-1",
    paymentTransactionId: "txn-1",
    orderId: "order-1",
    merchantReference: "COTZ-PAY-20260806-000001",
    successIndicator: "si-1",
  });

  assert.equal(readNmbCheckoutContext()?.paymentTransactionId, "txn-1");
  assert.equal(peekNmbPendingPaymentId(), "txn-1");
  assert.equal(memory.local.get("china-order-tz-nmb-checkout-durable")?.includes("txn-1"), true);
});

test("readNmbCheckoutContext falls back to durable storage when session is empty", () => {
  installStorageMocks();

  saveNmbCheckoutContext({
    paymentId: "txn-2",
    paymentTransactionId: "txn-2",
    orderId: "order-2",
    successIndicator: "si-2",
  });

  memory.session.clear();

  const recovered = readNmbCheckoutContext();
  assert.equal(recovered?.paymentTransactionId, "txn-2");
  assert.equal(recovered?.orderId, "order-2");
  assert.equal(recovered?.successIndicator, "si-2");
});
