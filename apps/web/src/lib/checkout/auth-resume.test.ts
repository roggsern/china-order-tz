import assert from "node:assert/strict";
import { describe, it } from "node:test";
import {
  consumeCheckoutPendingAuth,
  isCheckoutReturnPath,
  markCheckoutPendingAuth,
} from "./auth-resume";

function withSessionStorage(run: () => void): void {
  const store = new Map<string, string>();
  const originalWindow = globalThis.window;

  globalThis.window = {
    sessionStorage: {
      getItem: (key: string) => store.get(key) ?? null,
      setItem: (key: string, value: string) => {
        store.set(key, value);
      },
      removeItem: (key: string) => {
        store.delete(key);
      },
      clear: () => {
        store.clear();
      },
      key: () => null,
      length: 0,
    },
  } as unknown as Window & typeof globalThis;

  try {
    run();
  } finally {
    globalThis.window = originalWindow;
  }
}

describe("checkout auth resume", () => {
  it("marks and consumes checkout pending auth once", () => {
    withSessionStorage(() => {
      markCheckoutPendingAuth();
      assert.equal(consumeCheckoutPendingAuth(), true);
      assert.equal(consumeCheckoutPendingAuth(), false);
    });
  });

  it("recognizes checkout return paths", () => {
    assert.equal(isCheckoutReturnPath("/checkout"), true);
    assert.equal(isCheckoutReturnPath("/checkout/payment"), true);
    assert.equal(isCheckoutReturnPath("/account"), false);
  });
});
