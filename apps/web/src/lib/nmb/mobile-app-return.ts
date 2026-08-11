/**
 * Bridge for mobile AuthSession / deep-link return after Website Hosted Checkout.
 * Flag is stored in sessionStorage on the merchant origin so it survives the
 * MPGS redirect back to /payment/return.
 */

export const NMB_MOBILE_APP_RETURN_STORAGE_KEY = "cotz_nmb_mobile_app_return";

export const NMB_MOBILE_APP_PAYMENT_RETURN_SCHEME = "chinaordertz://payment-return";

export function markNmbMobileAppReturn(): void {
  if (typeof window === "undefined") return;
  try {
    window.sessionStorage.setItem(NMB_MOBILE_APP_RETURN_STORAGE_KEY, "1");
  } catch {
    // Private mode / blocked storage — AuthSession dismiss + mobile refresh still works.
  }
}

export function clearNmbMobileAppReturn(): void {
  if (typeof window === "undefined") return;
  try {
    window.sessionStorage.removeItem(NMB_MOBILE_APP_RETURN_STORAGE_KEY);
  } catch {
    // ignore
  }
}

export function isNmbMobileAppReturnMarked(): boolean {
  if (typeof window === "undefined") return false;
  try {
    return window.sessionStorage.getItem(NMB_MOBILE_APP_RETURN_STORAGE_KEY) === "1";
  } catch {
    return false;
  }
}

type SearchLike = string | URLSearchParams | { toString(): string };

/** Build chinaordertz://payment-return?... from current or provided search params. */
export function buildNmbMobileAppPaymentReturnUrl(search: SearchLike = ""): string {
  let params: URLSearchParams;
  if (typeof search === "string") {
    params = new URLSearchParams(search.startsWith("?") ? search.slice(1) : search);
  } else if (search instanceof URLSearchParams) {
    params = search;
  } else {
    params = new URLSearchParams(search.toString());
  }
  const query = params.toString();
  return query
    ? `${NMB_MOBILE_APP_PAYMENT_RETURN_SCHEME}?${query}`
    : NMB_MOBILE_APP_PAYMENT_RETURN_SCHEME;
}

/**
 * If this browser session was started from the mobile app, hand off to the
 * app deep link and return true (caller should stop web return UI).
 */
export function redirectToNmbMobileAppReturnIfNeeded(search?: SearchLike): boolean {
  if (!isNmbMobileAppReturnMarked()) {
    return false;
  }
  clearNmbMobileAppReturn();
  const href = buildNmbMobileAppPaymentReturnUrl(
    search ?? (typeof window !== "undefined" ? window.location.search : ""),
  );
  if (typeof window !== "undefined") {
    window.location.href = href;
  }
  return true;
}
