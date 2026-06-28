import type { MpesaConfig, PaymentMode } from "@/lib/payments/types";
import { getAppUrl, isDevelopment, isProduction, requireAppUrl } from "@/lib/config/env";

export type { PaymentMode, MpesaConfig };

export function getPaymentMode(): PaymentMode {
  const explicit = process.env.PAYMENT_MODE?.trim().toLowerCase();
  if (explicit === "live" || explicit === "test") {
    return explicit;
  }

  // Production never defaults to test mode — set PAYMENT_MODE=test explicitly for staging.
  if (isProduction()) {
    return "live";
  }

  const hasCredentials =
    Boolean(process.env.MPESA_CONSUMER_KEY) &&
    Boolean(process.env.MPESA_CONSUMER_SECRET) &&
    Boolean(process.env.MPESA_SHORTCODE) &&
    Boolean(process.env.MPESA_PASSKEY);

  return hasCredentials ? "live" : "test";
}

export function isPaymentTestMode(): boolean {
  return getPaymentMode() === "test";
}

export function isPaymentLiveMode(): boolean {
  return getPaymentMode() === "live";
}

export function getMpesaConfig(): MpesaConfig {
  const appUrl = getAppUrl() || (isDevelopment() ? "http://localhost:3000" : "");

  const callbackUrl =
    process.env.MPESA_CALLBACK_URL?.trim() ||
    (appUrl ? `${appUrl}/api/payments/mpesa/callback` : "");

  return {
    consumerKey: process.env.MPESA_CONSUMER_KEY ?? "",
    consumerSecret: process.env.MPESA_CONSUMER_SECRET ?? "",
    shortcode: process.env.MPESA_SHORTCODE ?? "",
    passkey: process.env.MPESA_PASSKEY ?? "",
    callbackUrl,
    baseUrl:
      process.env.MPESA_BASE_URL ??
      (getPaymentMode() === "live"
        ? "https://api.safaricom.co.ke"
        : "https://sandbox.safaricom.co.ke"),
    stkPushPath: "/mpesa/stkpush/v1/processrequest",
    oauthPath: "/oauth/v1/generate?grant_type=client_credentials",
  };
}

export function assertMpesaLiveConfig(): MpesaConfig {
  const config = getMpesaConfig();
  const missing: string[] = [];

  if (!config.consumerKey) missing.push("MPESA_CONSUMER_KEY");
  if (!config.consumerSecret) missing.push("MPESA_CONSUMER_SECRET");
  if (!config.shortcode) missing.push("MPESA_SHORTCODE");
  if (!config.passkey) missing.push("MPESA_PASSKEY");

  if (missing.length > 0) {
    throw new Error(`Missing M-Pesa configuration: ${missing.join(", ")}`);
  }

  if (isProduction()) {
    requireAppUrl();
    if (!config.callbackUrl.startsWith("https://")) {
      throw new Error("MPESA_CALLBACK_URL must use HTTPS in production.");
    }
  }

  return config;
}

export function canUseLiveMpesa(): boolean {
  if (isPaymentTestMode()) {
    return false;
  }

  const config = getMpesaConfig();
  return Boolean(
    config.consumerKey && config.consumerSecret && config.shortcode && config.passkey,
  );
}

export const TEST_STK_AUTO_COMPLETE_MS = Number.parseInt(
  process.env.PAYMENT_TEST_AUTO_COMPLETE_MS ?? "2500",
  10,
);
