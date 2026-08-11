import assert from "node:assert/strict";
import test from "node:test";
import {
  buildNmbMobileAppPaymentReturnUrl,
  clearNmbMobileAppReturn,
  isNmbMobileAppReturnMarked,
  markNmbMobileAppReturn,
  NMB_MOBILE_APP_PAYMENT_RETURN_SCHEME,
  NMB_MOBILE_APP_RETURN_STORAGE_KEY,
  redirectToNmbMobileAppReturnIfNeeded,
} from "./mobile-app-return";

test("buildNmbMobileAppPaymentReturnUrl preserves query for deep-link handoff", () => {
  assert.equal(
    buildNmbMobileAppPaymentReturnUrl("resultIndicator=ri-1&paymentTransactionId=txn-1"),
    `${NMB_MOBILE_APP_PAYMENT_RETURN_SCHEME}?resultIndicator=ri-1&paymentTransactionId=txn-1`,
  );
  assert.equal(
    buildNmbMobileAppPaymentReturnUrl(""),
    NMB_MOBILE_APP_PAYMENT_RETURN_SCHEME,
  );
});

test("mobile app return flag round-trips in sessionStorage", () => {
  const store = new Map<string, string>();
  (globalThis as { window?: unknown }).window = {
    sessionStorage: {
      getItem: (key: string) => store.get(key) ?? null,
      setItem: (key: string, value: string) => {
        store.set(key, value);
      },
      removeItem: (key: string) => {
        store.delete(key);
      },
    },
    location: { href: "", search: "?resultIndicator=ri-9" },
  };

  assert.equal(isNmbMobileAppReturnMarked(), false);
  markNmbMobileAppReturn();
  assert.equal(store.get(NMB_MOBILE_APP_RETURN_STORAGE_KEY), "1");
  assert.equal(isNmbMobileAppReturnMarked(), true);

  const redirected = redirectToNmbMobileAppReturnIfNeeded();
  assert.equal(redirected, true);
  assert.equal(isNmbMobileAppReturnMarked(), false);
  assert.match(
    String((globalThis as { window: { location: { href: string } } }).window.location.href),
    /^chinaordertz:\/\/payment-return\?/,
  );

  clearNmbMobileAppReturn();
  assert.equal(redirectToNmbMobileAppReturnIfNeeded(), false);
});
