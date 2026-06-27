export type PaymentMode = "test" | "live";

export type MpesaConfig = {
  consumerKey: string;
  consumerSecret: string;
  shortcode: string;
  passkey: string;
  callbackUrl: string;
  /** Daraja base URL — sandbox or production */
  baseUrl: string;
};

function readPaymentMode(): PaymentMode {
  const explicit = process.env.PAYMENT_MODE?.toLowerCase();
  if (explicit === "live" || explicit === "test") {
    return explicit;
  }

  const hasCredentials =
    Boolean(process.env.MPESA_CONSUMER_KEY) &&
    Boolean(process.env.MPESA_CONSUMER_SECRET) &&
    Boolean(process.env.MPESA_SHORTCODE) &&
    Boolean(process.env.MPESA_PASSKEY);

  return hasCredentials ? "live" : "test";
}

export function getPaymentMode(): PaymentMode {
  return readPaymentMode();
}

export function isPaymentTestMode(): boolean {
  return readPaymentMode() === "test";
}

export function getMpesaConfig(): MpesaConfig {
  const appUrl = process.env.NEXT_PUBLIC_APP_URL ?? process.env.APP_URL ?? "http://localhost:3000";

  return {
    consumerKey: process.env.MPESA_CONSUMER_KEY ?? "",
    consumerSecret: process.env.MPESA_CONSUMER_SECRET ?? "",
    shortcode: process.env.MPESA_SHORTCODE ?? "",
    passkey: process.env.MPESA_PASSKEY ?? "",
    callbackUrl: process.env.MPESA_CALLBACK_URL ?? `${appUrl}/api/payments/callback`,
    baseUrl:
      process.env.MPESA_BASE_URL ??
      (readPaymentMode() === "live"
        ? "https://api.safaricom.co.ke"
        : "https://sandbox.safaricom.co.ke"),
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

  return config;
}
