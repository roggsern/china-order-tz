import { getCustomerApiToken } from "@/lib/api/customer-auth";

export class CustomerEmailChangeError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "CustomerEmailChangeError";
  }
}

type ApiResponse<T = unknown> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export type CustomerProfileSecurity = {
  email: string;
  email_verified_at?: string | null;
  pending_email?: string | null;
  pending_email_expires_at?: string | null;
  first_name?: string | null;
  last_name?: string | null;
  phone?: string | null;
};

function extractErrorMessage(payload: ApiResponse, fallback: string): string {
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

export async function fetchCustomerSecurityProfile(): Promise<CustomerProfileSecurity> {
  const token = getCustomerApiToken();
  if (!token) {
    throw new CustomerEmailChangeError("Sign in to manage your email.", 401);
  }

  const response = await fetch("/api/profile", {
    method: "GET",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    cache: "no-store",
  });

  const payload = await parseJson<CustomerProfileSecurity>(response);
  if (!response.ok || payload.success === false || !payload.data) {
    throw new CustomerEmailChangeError(
      extractErrorMessage(payload, "Unable to load account security details."),
      response.status,
      payload.errors,
    );
  }

  return payload.data;
}

export async function requestCustomerEmailChange(input: {
  newEmail: string;
  currentPassword: string;
}): Promise<{ message: string; pendingEmail: string; expiresAt?: string | null }> {
  const token = getCustomerApiToken();
  if (!token) {
    throw new CustomerEmailChangeError("Sign in to change your email.", 401);
  }

  const response = await fetch("/api/account/email-change", {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({
      new_email: input.newEmail.trim().toLowerCase(),
      current_password: input.currentPassword,
    }),
    cache: "no-store",
  });

  const payload = await parseJson<{ pending_email?: string; expires_at?: string | null }>(response);
  if (!response.ok || payload.success === false) {
    throw new CustomerEmailChangeError(
      extractErrorMessage(payload, "Unable to start email change."),
      response.status,
      payload.errors,
    );
  }

  return {
    message: payload.message?.trim() || "We sent a confirmation link to your new email address.",
    pendingEmail: payload.data?.pending_email || input.newEmail.trim().toLowerCase(),
    expiresAt: payload.data?.expires_at ?? null,
  };
}

export async function confirmCustomerEmailChange(tokenValue: string): Promise<{
  message: string;
  email: string;
}> {
  const authToken = getCustomerApiToken();
  const headers: Record<string, string> = {
    Accept: "application/json",
    "Content-Type": "application/json",
  };
  if (authToken) {
    headers.Authorization = `Bearer ${authToken}`;
  }

  const response = await fetch("/api/account/email-change/confirm", {
    method: "POST",
    headers,
    body: JSON.stringify({ token: tokenValue }),
    cache: "no-store",
  });

  const payload = await parseJson<{ email?: string }>(response);
  if (!response.ok || payload.success === false) {
    throw new CustomerEmailChangeError(
      extractErrorMessage(payload, "Unable to confirm email change."),
      response.status,
      payload.errors,
    );
  }

  return {
    message: payload.message?.trim() || "Your email address has been updated.",
    email: payload.data?.email || "",
  };
}
