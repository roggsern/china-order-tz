import assert from "node:assert/strict";
import { afterEach, test } from "node:test";
import {
  clearCustomerApiToken,
  getCustomerApiToken,
  saveCustomerApiToken,
} from "@/lib/api/customer-auth";
import { clearStaleCustomerAuth } from "@/lib/customer/clear-stale-customer-auth";
import {
  clearCustomerSession,
  getCustomerSession,
  saveCustomerSession,
} from "@/lib/customer/session";

async function withLocalStorage(run: () => Promise<void> | void): Promise<void> {
  const storage = new Map<string, string>();
  const events: string[] = [];
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
    dispatchEvent: (event: Event) => {
      events.push(event.type);
      return true;
    },
  } as unknown as Window & typeof globalThis;

  try {
    await run();
    assert.ok(events.includes("customer-session-updated"));
  } finally {
    globalThis.window = originalWindow;
  }
}

afterEach(() => {
  clearCustomerApiToken();
  clearCustomerSession();
});

test("clearStaleCustomerAuth removes token and session without leaving authenticated claims", async () => {
  await withLocalStorage(() => {
    saveCustomerApiToken("stale-bearer-token");
    saveCustomerSession({ email: "buyer@example.com", name: "Buyer" });

    clearStaleCustomerAuth();

    assert.equal(getCustomerApiToken(), null);
    assert.equal(getCustomerSession(), null);
  });
});

test("after stale auth clear, subsequent cart auth header helper sees no Bearer", async () => {
  await withLocalStorage(() => {
    saveCustomerApiToken("stale-bearer-token");
    clearStaleCustomerAuth();
    assert.equal(getCustomerApiToken(), null);
  });
});
