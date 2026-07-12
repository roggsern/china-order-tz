import { getApiUrl, getAppUrl } from "@/lib/config/env";

const DEFAULT_SANDBOX_GATEWAY = "https://test-nmbbank.mtf.gateway.mastercard.com";

/** MPGS gateway base URL (no trailing slash). */
export function getNmbGatewayUrl(): string {
  const fromEnv = process.env.NEXT_PUBLIC_NMB_GATEWAY_URL?.trim();

  if (fromEnv) {
    return fromEnv.replace(/\/$/, "");
  }

  return DEFAULT_SANDBOX_GATEWAY;
}

/** Hosted Checkout JavaScript SDK URL for the configured gateway. */
export function getNmbCheckoutScriptUrl(): string {
  const fromEnv = process.env.NEXT_PUBLIC_NMB_CHECKOUT_JS_URL?.trim();

  if (fromEnv) {
    return fromEnv;
  }

  return `${getNmbGatewayUrl()}/static/checkout/checkout.min.js`;
}

/** Browser return URL after Hosted Checkout — must match NMB_RETURN_URL on the API. */
export function getNmbReturnUrl(): string {
  const fromEnv = process.env.NEXT_PUBLIC_NMB_RETURN_URL?.trim();

  if (fromEnv) {
    return fromEnv.replace(/\/$/, "");
  }

  return `${getAppUrl()}/payment/return`;
}

export function getLaravelApiBaseUrl(): string {
  return `${getApiUrl()}/api/v1`;
}
