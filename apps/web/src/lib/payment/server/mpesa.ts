import { assertMpesaLiveConfig, getMpesaConfig, isPaymentTestMode } from "@/lib/payment/server/config";

type OAuthResponse = {
  access_token: string;
  expires_in: string;
};

type StkPushResponse = {
  MerchantRequestID: string;
  CheckoutRequestID: string;
  ResponseCode: string;
  ResponseDescription: string;
  CustomerMessage: string;
};

export function normalizeMpesaPhone(phone: string): string {
  const digits = phone.replace(/\D/g, "");

  if (digits.startsWith("255") && digits.length === 12) {
    return digits;
  }

  if (digits.startsWith("0") && digits.length === 10) {
    return `255${digits.slice(1)}`;
  }

  if (digits.length === 9 && digits.startsWith("7")) {
    return `255${digits}`;
  }

  return digits;
}

function buildPassword(shortcode: string, passkey: string, timestamp: string): string {
  return Buffer.from(`${shortcode}${passkey}${timestamp}`).toString("base64");
}

export async function getMpesaAccessToken(): Promise<string> {
  const config = assertMpesaLiveConfig();
  const credentials = Buffer.from(`${config.consumerKey}:${config.consumerSecret}`).toString(
    "base64",
  );

  const response = await fetch(`${config.baseUrl}/oauth/v1/generate?grant_type=client_credentials`, {
    method: "GET",
    headers: {
      Authorization: `Basic ${credentials}`,
    },
    cache: "no-store",
  });

  if (!response.ok) {
    throw new Error(`M-Pesa OAuth failed (${response.status})`);
  }

  const data = (await response.json()) as OAuthResponse;
  if (!data.access_token) {
    throw new Error("M-Pesa OAuth response missing access_token");
  }

  return data.access_token;
}

export async function initiateMpesaStkPush(input: {
  phone: string;
  amount: number;
  accountReference: string;
  description: string;
}): Promise<StkPushResponse> {
  const config = assertMpesaLiveConfig();
  const accessToken = await getMpesaAccessToken();
  const timestamp = new Date()
    .toISOString()
    .replace(/[-:TZ.]/g, "")
    .slice(0, 14);
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

  const response = await fetch(`${config.baseUrl}/mpesa/stkpush/v1/processrequest`, {
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

  return (await response.json()) as StkPushResponse;
}

export function createTestStkPushResponse(): StkPushResponse {
  const suffix = Date.now().toString(36).toUpperCase();
  return {
    MerchantRequestID: `TEST-MR-${suffix}`,
    CheckoutRequestID: `TEST-CR-${suffix}`,
    ResponseCode: "0",
    ResponseDescription: "Accept the service request successfully.",
    CustomerMessage: "Check your phone to enter M-Pesa PIN (test mode).",
  };
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
