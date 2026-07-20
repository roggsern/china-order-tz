import type {
  DarajaOAuthResponse,
  DarajaStkPushResponse,
  MpesaCallbackResult,
} from "@/lib/payments/types";
import { assertMpesaLiveConfig, canUseLiveMpesa, getMpesaConfig } from "@/lib/payments/config";

import { toPaymentGatewayPhone } from "@/lib/phone";

export function normalizeMpesaPhone(phone: string): string {
  return toPaymentGatewayPhone(phone);
}

function buildPassword(shortcode: string, passkey: string, timestamp: string): string {
  return Buffer.from(`${shortcode}${passkey}${timestamp}`).toString("base64");
}

function darajaTimestamp(): string {
  return new Date().toISOString().replace(/[-:TZ.]/g, "").slice(0, 14);
}

export async function getMpesaAccessToken(): Promise<string> {
  const config = assertMpesaLiveConfig();
  const credentials = Buffer.from(`${config.consumerKey}:${config.consumerSecret}`).toString(
    "base64",
  );

  const response = await fetch(`${config.baseUrl}${config.oauthPath}`, {
    method: "GET",
    headers: { Authorization: `Basic ${credentials}` },
    cache: "no-store",
  });

  if (!response.ok) {
    throw new Error(`M-Pesa OAuth failed (${response.status})`);
  }

  const data = (await response.json()) as DarajaOAuthResponse;
  if (!data.access_token) {
    throw new Error("M-Pesa OAuth response missing access_token");
  }

  return data.access_token;
}

/**
 * Live Safaricom Daraja STK Push — requires MPESA_* credentials and PAYMENT_MODE=live.
 */
export async function initiateSTKPush(input: {
  phone: string;
  amount: number;
  accountReference: string;
  description: string;
}): Promise<DarajaStkPushResponse> {
  const config = assertMpesaLiveConfig();
  const accessToken = await getMpesaAccessToken();
  const timestamp = darajaTimestamp();
  const password = buildPassword(config.shortcode, config.passkey, timestamp);
  const phone = normalizeMpesaPhone(input.phone);

  const payload = {
    BusinessShortCode: config.shortcode,
    Password: password,
    Timestamp: timestamp,
    TransactionType: "CustomerPayBillOnline",
    Amount: Math.round(input.amount),
    PartyA: phone,
    PartyB: config.shortcode,
    PhoneNumber: phone,
    CallBackURL: config.callbackUrl,
    AccountReference: input.accountReference.slice(0, 12),
    TransactionDesc: input.description.slice(0, 13),
  };

  const response = await fetch(`${config.baseUrl}${config.stkPushPath}`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${accessToken}`,
      "Content-Type": "application/json",
    },
    body: JSON.stringify(payload),
    cache: "no-store",
  });

  if (!response.ok) {
    const body = await response.text();
    throw new Error(`M-Pesa STK Push failed (${response.status}): ${body}`);
  }

  return (await response.json()) as DarajaStkPushResponse;
}

export function createTestStkPushResponse(): DarajaStkPushResponse {
  const suffix = Date.now().toString(36).toUpperCase();
  return {
    MerchantRequestID: `TEST-MR-${suffix}`,
    CheckoutRequestID: `TEST-CR-${suffix}`,
    ResponseCode: "0",
    ResponseDescription: "Accept the service request successfully.",
    CustomerMessage: "Check your phone to enter M-Pesa PIN (test mode).",
  };
}

export function generateTestMpesaReceipt(): string {
  return `MPESA-TEST-${Date.now().toString(36).toUpperCase()}`;
}

/**
 * Test-mode STK response — no Daraja call; returns a successful STK acknowledgement.
 */
export async function simulateSTKPush(input: {
  phone: string;
  amount: number;
  accountReference: string;
}): Promise<DarajaStkPushResponse> {
  void input;
  await new Promise((resolve) => setTimeout(resolve, 300));
  return createTestStkPushResponse();
}

export function shouldUseLiveStkPush(): boolean {
  return canUseLiveMpesa();
}

export function getDarajaEndpointSummary(): {
  oauth: string;
  stkPush: string;
  callbackUrl: string;
} {
  const config = getMpesaConfig();
  return {
    oauth: `${config.baseUrl}${config.oauthPath}`,
    stkPush: `${config.baseUrl}${config.stkPushPath}`,
    callbackUrl: config.callbackUrl,
  };
}

/**
 * Parse Safaricom STK callback payload into a normalized result.
 * @deprecated Use parseStkCallbackPayload from @/lib/payments/mpesa-callback
 */
export { parseStkCallbackPayload, isValidMpesaCallbackPayload } from "@/lib/payments/mpesa-callback";
export type { ParsedMpesaStkCallback } from "@/lib/payments/types";

export function buildCallbackAck(result: MpesaCallbackResult): {
  ResultCode: number;
  ResultDesc: string;
} {
  return {
    ResultCode: result.handled ? 0 : 1,
    ResultDesc: result.message,
  };
}
