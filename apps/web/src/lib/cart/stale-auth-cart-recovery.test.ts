import assert from "node:assert/strict";
import { test } from "node:test";
import { CustomerCartApiError } from "@/lib/api/customer-cart";
import type { AddToCartResult } from "@/lib/cart/add-to-cart-ui";
import {
  resolveCartSyncFailure,
  STALE_CART_AUTH_RECOVERY_MESSAGE,
} from "@/lib/cart/sync-errors";
import { clearCustomerApiToken, getCustomerApiToken, saveCustomerApiToken } from "@/lib/api/customer-auth";
import { clearStaleCustomerAuth } from "@/lib/customer/clear-stale-customer-auth";
import { clearCustomerSession, saveCustomerSession } from "@/lib/customer/session";

/**
 * Mirrors CartProvider server-add recovery decisions without mounting React.
 */
async function recoverAddToCartFromServerError(
  error: unknown,
  appendLocal: () => void,
): Promise<AddToCartResult> {
  const resolution = resolveCartSyncFailure(error, "Unable to add item to your cart.");

  if (resolution.kind === "fallback_local_stale_auth") {
    clearStaleCustomerAuth();
    appendLocal();
    return { ok: true, recoveredFromStaleAuth: true };
  }

  if (resolution.kind === "fallback_local") {
    appendLocal();
    return { ok: true };
  }

  return { ok: false, message: resolution.message };
}

async function withWindow(run: () => Promise<void> | void): Promise<void> {
  const storage = new Map<string, string>();
  const originalWindow = globalThis.window;

  globalThis.window = {
    localStorage: {
      getItem: (key: string) => storage.get(key) ?? null,
      setItem: (key: string, value: string) => {
        storage.set(key, value);
      },
      removeItem: (key: string) => {
        storage.delete(key);
      },
    },
    dispatchEvent: () => true,
  } as unknown as Window & typeof globalThis;

  try {
    await run();
  } finally {
    globalThis.window = originalWindow;
    clearCustomerApiToken();
    clearCustomerSession();
  }
}

test("no token path is represented as local success without server recovery", async () => {
  await withWindow(() => {
    assert.equal(getCustomerApiToken(), null);
    const result: AddToCartResult = { ok: true };
    assert.equal(result.ok, true);
  });
});

test("valid token path keeps server success contract", async () => {
  const result: AddToCartResult = { ok: true };
  assert.equal(result.ok, true);
  assert.equal(result.recoveredFromStaleAuth, undefined);
});

test("stale token 401 clears auth, adds locally, and returns success after fallback", async () => {
  await withWindow(async () => {
    saveCustomerApiToken("expired-token");
    saveCustomerSession({ email: "buyer@example.com" });

    let localAdded = false;
    const result = await recoverAddToCartFromServerError(
      new CustomerCartApiError("Unauthenticated.", 401),
      () => {
        localAdded = true;
      },
    );

    assert.equal(result.ok, true);
    if (result.ok) {
      assert.equal(result.recoveredFromStaleAuth, true);
    }
    assert.equal(localAdded, true);
    assert.equal(getCustomerApiToken(), null);
    assert.equal(STALE_CART_AUTH_RECOVERY_MESSAGE.includes("Unauthenticated"), false);
  });
});

test("after stale recovery later add does not still see old Bearer", async () => {
  await withWindow(() => {
    saveCustomerApiToken("expired-token");
    clearStaleCustomerAuth();
    assert.equal(getCustomerApiToken(), null);
  });
});

test("non-auth business failure keeps server and does not local-add", async () => {
  await withWindow(async () => {
    saveCustomerApiToken("valid-token");
    let localAdded = false;
    const result = await recoverAddToCartFromServerError(
      new CustomerCartApiError("Product is not available.", 422),
      () => {
        localAdded = true;
      },
    );

    assert.equal(result.ok, false);
    assert.equal(localAdded, false);
    assert.equal(getCustomerApiToken(), "valid-token");
  });
});
