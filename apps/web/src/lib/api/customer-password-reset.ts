export class CustomerPasswordResetError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly errors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "CustomerPasswordResetError";
  }
}

type ApiResponse = {
  success?: boolean;
  message?: string;
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

export async function requestPasswordReset(email: string): Promise<{ message: string }> {
  const response = await fetch("/api/auth/forgot-password", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({ email: email.trim().toLowerCase() }),
    cache: "no-store",
  });

  let payload: ApiResponse = {};
  try {
    payload = (await response.json()) as ApiResponse;
  } catch {
    payload = {};
  }

  if (!response.ok) {
    throw new CustomerPasswordResetError(
      extractErrorMessage(payload, "Unable to send reset instructions. Please try again."),
      response.status,
      payload.errors,
    );
  }

  return {
    message:
      payload.message?.trim() ||
      "If an account exists for that email, password reset instructions have been sent.",
  };
}

export async function resetCustomerPassword(input: {
  email: string;
  token: string;
  password: string;
  passwordConfirmation: string;
}): Promise<{ message: string }> {
  const response = await fetch("/api/auth/reset-password", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      email: input.email.trim().toLowerCase(),
      token: input.token,
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

  if (!response.ok) {
    throw new CustomerPasswordResetError(
      extractErrorMessage(payload, "Unable to reset password. The link may be invalid or expired."),
      response.status,
      payload.errors,
    );
  }

  return {
    message:
      payload.message?.trim() ||
      "Your password has been reset. You can sign in with your new password.",
  };
}
