import { getCustomerApiToken } from "@/lib/api/customer-auth";

export class CustomerAccountCloseError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "CustomerAccountCloseError";
  }
}

type ApiResponse = {
  success?: boolean;
  message?: string;
  requires_reauthentication?: boolean;
  already_closed?: boolean;
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

export async function closeCustomerAccount(input: {
  currentPassword: string;
  acknowledge: boolean;
}): Promise<{
  message: string;
  requiresReauthentication: boolean;
  alreadyClosed: boolean;
}> {
  const token = getCustomerApiToken();
  if (!token) {
    throw new CustomerAccountCloseError("Sign in to close your account.", 401);
  }

  const response = await fetch("/api/account/close", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: JSON.stringify({
      current_password: input.currentPassword,
      acknowledge: input.acknowledge,
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
    throw new CustomerAccountCloseError(
      extractErrorMessage(payload, "Unable to close your account. Please try again."),
      response.status,
      payload.errors,
    );
  }

  return {
    message:
      payload.message?.trim() ||
      "Your account has been closed. You have been signed out.",
    requiresReauthentication: payload.requires_reauthentication !== false,
    alreadyClosed: payload.already_closed === true,
  };
}
