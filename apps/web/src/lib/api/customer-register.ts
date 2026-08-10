export type CustomerRegisterUser = {
  id: string;
  name: string;
  email: string;
  phone?: string | null;
};

export type CustomerRegisterResponse = {
  success: boolean;
  message?: string;
  token?: string;
  token_type?: string;
  data?: CustomerRegisterUser;
  errors?: Record<string, string[]>;
};

export type CustomerRegisterResult = {
  token: string;
  user: CustomerRegisterUser;
};

export class CustomerRegisterError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
    public readonly fieldErrors?: Record<string, string[]>,
  ) {
    super(message);
    this.name = "CustomerRegisterError";
  }
}

function extractRegisterErrorMessage(payload: CustomerRegisterResponse): string {
  if (payload.message?.trim()) {
    return payload.message.trim();
  }

  if (payload.errors) {
    const firstFieldError = Object.values(payload.errors).flat()[0]?.trim();
    if (firstFieldError) {
      return firstFieldError;
    }
  }

  return "Unable to create your account. Please check the form and try again.";
}

/** Maps browser/network failures to a customer-safe registration message. */
export function mapRegisterNetworkError(error: unknown): CustomerRegisterError {
  const message =
    error instanceof Error ? error.message.trim() : "";

  if (/failed to fetch|networkerror|network request failed|load failed|aborted|timeout/i.test(message)) {
    return new CustomerRegisterError(
      "Unable to reach the server. If you just created an account, try signing in with the same email.",
      0,
    );
  }

  return new CustomerRegisterError(
    message || "Unable to create your account. Please try again.",
    0,
  );
}

export type CustomerRegisterInput = {
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  password: string;
  passwordConfirmation: string;
};

/**
 * Registers a customer via POST /api/v1/register through the Next.js BFF proxy.
 */
export async function registerCustomer(
  input: CustomerRegisterInput,
): Promise<CustomerRegisterResult> {
  const name = `${input.firstName.trim()} ${input.lastName.trim()}`.trim();

  let response: Response;

  try {
    response = await fetch("/api/auth/register", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        name,
        first_name: input.firstName.trim(),
        last_name: input.lastName.trim(),
        email: input.email.trim(),
        phone: input.phone.trim() || undefined,
        password: input.password,
        password_confirmation: input.passwordConfirmation,
      }),
    });
  } catch (error) {
    throw mapRegisterNetworkError(error);
  }

  let payload: CustomerRegisterResponse;

  try {
    payload = (await response.json()) as CustomerRegisterResponse;
  } catch {
    throw new CustomerRegisterError(
      "Unable to read the registration response. If you just created an account, try signing in.",
      response.status,
    );
  }

  if (!response.ok || payload.success === false) {
    throw new CustomerRegisterError(
      extractRegisterErrorMessage(payload),
      response.status,
      payload.errors,
    );
  }

  const token = payload.token?.trim();
  const user = payload.data;

  if (!token) {
    throw new CustomerRegisterError("Registration response did not include an API token.");
  }

  if (!user?.email?.trim()) {
    throw new CustomerRegisterError("Registration response did not include customer details.");
  }

  return {
    token,
    user: {
      id: user.id,
      name: user.name,
      email: user.email.trim(),
      phone: user.phone,
    },
  };
}
