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

  const response = await fetch("/api/auth/register", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      name,
      email: input.email.trim(),
      phone: input.phone.trim() || undefined,
      password: input.password,
      password_confirmation: input.passwordConfirmation,
    }),
  });

  const payload = (await response.json()) as CustomerRegisterResponse;

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
