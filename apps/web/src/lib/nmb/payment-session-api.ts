import { getCustomerApiToken } from "@/lib/api/customer-auth";
import type { NmbPaymentSessionResponse } from "@/lib/nmb/types";

export class NmbPaymentSessionError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "NmbPaymentSessionError";
  }
}

/**
 * Initiates an existing Laravel payment via POST /api/v1/payments/{paymentId}/initiate.
 * Uses the Next.js BFF proxy to avoid cross-origin issues.
 */
export async function initiateNmbPaymentSession(
  paymentId: string,
  token?: string | null,
): Promise<NmbPaymentSessionResponse> {
  const authToken = token ?? getCustomerApiToken();

  if (!authToken) {
    throw new NmbPaymentSessionError("Please sign in to continue with payment.");
  }

  const response = await fetch("/api/payments/nmb/initiate", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${authToken}`,
    },
    body: JSON.stringify({ paymentId }),
  });

  const payload = (await response.json()) as NmbPaymentSessionResponse & {
    message?: string;
  };

  if (!response.ok || !payload.success) {
    throw new NmbPaymentSessionError(
      payload.message ?? "Unable to start NMB payment session.",
      response.status,
    );
  }

  if (!payload.data?.gateway_session_id) {
    throw new NmbPaymentSessionError("NMB did not return a checkout session id.");
  }

  return payload;
}
