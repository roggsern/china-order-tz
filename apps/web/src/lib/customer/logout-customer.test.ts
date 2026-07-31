import assert from "node:assert/strict";
import { afterEach, describe, it, mock } from "node:test";
import {
  clearCustomerApiToken,
  getCustomerApiToken,
  saveCustomerApiToken,
} from "@/lib/api/customer-auth";
import { logoutCustomer } from "@/lib/customer/logout-customer";
import {
  clearCustomerSession,
  getCustomerSession,
  saveCustomerSession,
} from "@/lib/customer/session";

async function withLocalStorage(run: () => Promise<void>): Promise<void> {
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
  }
}

describe("customer logout end-to-end", () => {
  afterEach(() => {
    mock.restoreAll();
  });

  it("calls logout API then clears local auth state", async () => {
    await withLocalStorage(async () => {
      saveCustomerApiToken("test-token-abc");
      saveCustomerSession({ email: "jane@example.com", name: "Jane" });

      const fetchMock = mock.method(globalThis, "fetch", async (input: RequestInfo | URL, init?: RequestInit) => {
        assert.equal(String(input), "/api/customer/logout");
        assert.equal(init?.method, "POST");
        const headers = init?.headers as Record<string, string>;
        assert.equal(headers.Authorization, "Bearer test-token-abc");
        return new Response(JSON.stringify({ success: true }), {
          status: 200,
          headers: { "Content-Type": "application/json" },
        });
      });

      const result = await logoutCustomer({ showToast: false });

      assert.equal(result.serverRevokeAttempted, true);
      assert.equal(result.serverRevokeOk, true);
      assert.equal(result.localCleared, true);
      assert.equal(getCustomerApiToken(), null);
      assert.equal(getCustomerSession(), null);
      assert.equal(fetchMock.mock.callCount(), 1);
    });
  });

  it("clears local state when logout API fails", async () => {
    await withLocalStorage(async () => {
      saveCustomerApiToken("stale-token");
      saveCustomerSession({ email: "jane@example.com" });

      mock.method(globalThis, "fetch", async () => {
        throw new Error("network down");
      });

      const result = await logoutCustomer({ showToast: false });

      assert.equal(result.serverRevokeAttempted, true);
      assert.equal(result.serverRevokeOk, false);
      assert.equal(result.localCleared, true);
      assert.equal(getCustomerApiToken(), null);
      assert.equal(getCustomerSession(), null);
    });
  });

  it("handles already-logged-out state without calling API", async () => {
    await withLocalStorage(async () => {
      clearCustomerApiToken();
      clearCustomerSession();

      const fetchMock = mock.method(globalThis, "fetch", async () => {
        throw new Error("fetch should not be called");
      });

      const result = await logoutCustomer({ showToast: false });

      assert.equal(result.serverRevokeAttempted, false);
      assert.equal(result.serverRevokeOk, false);
      assert.equal(result.localCleared, true);
      assert.equal(fetchMock.mock.callCount(), 0);
    });
  });
});
