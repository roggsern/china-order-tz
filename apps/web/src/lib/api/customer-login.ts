export type CustomerLoginUser = {
  id: string;
  name: string;
  email: string;
  phone?: string | null;
};

export type CustomerLoginResponse = {
  success: boolean;
  message?: string;
  token?: string;
  token_type?: string;
  data?: CustomerLoginUser;
  errors?: Record<string, string[]>;
};

export type CustomerLoginResult = {
  token: string;
  user: CustomerLoginUser;
};

export class CustomerLoginError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CustomerLoginError";
  }
}

function extractLoginErrorMessage(payload: CustomerLoginResponse): string {
  if (payload.message?.trim()) {
    const message = payload.message.trim();
    if (/unauthenticated|unauthorized|forbidden|credentials|invalid/i.test(message)) {
      return "Incorrect email or password. Please try again.";
    }
    return message;
  }

  if (payload.errors) {
    const firstFieldError = Object.values(payload.errors).flat()[0]?.trim();
    if (firstFieldError) {
      return firstFieldError;
    }
  }

  return "Unable to sign in. Please check your credentials and try again.";
}

/**
 * Authenticates a customer via POST /api/v1/login through the Next.js BFF proxy.
 */
export async function loginCustomer(credentials: {
  email: string;
  password: string;
}): Promise<CustomerLoginResult> {
  const response = await fetch("/api/auth/login", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Accept: "application/json",
    },
    body: JSON.stringify({
      email: credentials.email.trim(),
      password: credentials.password,
    }),
  });

  const payload = (await response.json()) as CustomerLoginResponse;

  if (!response.ok || !payload.success) {
    throw new CustomerLoginError(extractLoginErrorMessage(payload), response.status);
  }

  const token = payload.token?.trim();
  const user = payload.data;

  if (!token) {
    throw new CustomerLoginError("Login response did not include an API token.");
  }

  if (!user?.email?.trim()) {
    throw new CustomerLoginError("Login response did not include customer details.");
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
