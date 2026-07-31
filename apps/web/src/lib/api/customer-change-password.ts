import { getCustomerApiToken } from "@/lib/api/customer-auth";

export class CustomerChangePasswordError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "CustomerChangePasswordError";
  }
}

type ApiResponse = {
  success?: boolean;
  message?: string;
  requires_reauthentication?: boolean;
  errors?: Record<string, string[]>;
};

function extractErrorMessage(payload: ApiResponse, fallback: string): string {
  if (payload.message?.trim()) {
    return payload.message.trim();
  }
  if (payload.errors) {
    const first = Object.values(payload.errors).flat()[0]?.trim();
    if (first) return first;
  }
  return fallback;
}

export async function changeCustomerPassword(input: {
  currentPassword: string;
  password: string;
  passwordConfirmation: string;
}): Promise<{ message: string; requiresReauthentication: boolean }> {
  const token = getCustomerApiToken();
  if (!token) {
    throw new CustomerChangePasswordError("Sign in to change your password.", 401);
  }

  const response = await fetch("/api/account/change-password", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({
      current_password: input.currentPassword,
      password: input.password,
      password_confirmation: input.passwordConfirmation,
    }),
    cache: "no-store",
  });

  let payload: ApiResponse = {};
  try {
    payload = (await response.json()) as ApiResponse;
  } catch {
    payload = {};
  }

  if (!response.ok || payload.success === false) {
    throw new CustomerChangePasswordError(
      extractErrorMessage(payload, "Unable to change password. Please try again."),
      response.status,
      payload.errors,
    );
  }

  return {
    message:
      payload.message?.trim() ||
      "Your password has been changed. Please sign in again.",
    requiresReauthentication: payload.requires_reauthentication !== false,
  };
}
