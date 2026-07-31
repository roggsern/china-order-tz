import { getCustomerApiToken } from "@/lib/api/customer-auth";

export class CustomerEmailVerificationError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "CustomerEmailVerificationError";
  }
}

type ApiResponse<T = unknown> = {
  success?: boolean;
  message?: string;
  already_verified?: boolean;
  data?: T;
  errors?: Record<string, string[]>;
};

function extractError(payload: ApiResponse, fallback: string): string {
  if (payload.message?.trim()) return payload.message.trim();
  if (payload.errors) {
    const first = Object.values(payload.errors).flat()[0]?.trim();
    if (first) return first;
  }
  return fallback;
}

async function parseJson<T>(response: Response): Promise<ApiResponse<T>> {
  try {
    return (await response.json()) as ApiResponse<T>;
  } catch {
    return {};
  }
}

export async function confirmEmailVerification(input: {
  id: string;
  hash: string;
  expires: string;
  signature: string;
}): Promise<{ message: string; alreadyVerified: boolean }> {
  const response = await fetch("/api/account/email/verify", {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(input),
    cache: "no-store",
  });

  const payload = await parseJson(response);
  if (!response.ok || payload.success === false) {
    throw new CustomerEmailVerificationError(
      extractError(payload, "Unable to verify email. The link may be invalid or expired."),
      response.status,
      payload.errors,
    );
  }

  return {
    message: payload.message?.trim() || "Your email address has been verified.",
    alreadyVerified: Boolean(payload.already_verified),
  };
}

export async function resendEmailVerification(): Promise<{
  message: string;
  alreadyVerified: boolean;
}> {
  const token = getCustomerApiToken();
  if (!token) {
    throw new CustomerEmailVerificationError("Sign in to resend verification.", 401);
  }

  const response = await fetch("/api/account/email/verify/resend", {
    method: "POST",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    cache: "no-store",
  });

  const payload = await parseJson(response);
  if (!response.ok || payload.success === false) {
    throw new CustomerEmailVerificationError(
      extractError(payload, "Unable to resend verification email."),
      response.status,
      payload.errors,
    );
  }

  return {
    message: payload.message?.trim() || "A verification link has been sent to your email address.",
    alreadyVerified: Boolean(payload.already_verified),
  };
}
