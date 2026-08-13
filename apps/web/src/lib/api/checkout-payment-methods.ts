import { getCustomerApiToken } from "@/lib/api/customer-auth";

export type CheckoutPaymentMethodRow = {
  code: string;
  enabled: boolean;
  available: boolean;
  selectable: boolean;
};

export type CheckoutPaymentAvailability = {
  default_provider: string;
  enabled_methods: string[];
  methods: CheckoutPaymentMethodRow[];
};

type ApiSuccessResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  errors?: Record<string, string[]>;
};

export class CheckoutPaymentMethodsApiError extends Error {
  constructor(
    message: string,
    public readonly statusCode?: number,
  ) {
    super(message);
    this.name = "CheckoutPaymentMethodsApiError";
  }
}

function formatError(payload: ApiSuccessResponse<unknown>, fallback: string): string {
  if (payload.message?.trim()) return payload.message.trim();
  if (payload.errors) {
    const first = Object.values(payload.errors).flat()[0];
    if (first?.trim()) return first.trim();
  }
  return fallback;
}

const PREFETCH_TTL_MS = 60_000;
let prefetched: {
  token: string;
  expiresAt: number;
  promise: Promise<CheckoutPaymentAvailability>;
} | null = null;

async function loadCheckoutPaymentMethods(
  authToken: string,
): Promise<CheckoutPaymentAvailability> {
  const response = await fetch("/api/payments/methods", {
    method: "GET",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${authToken}`,
    },
    cache: "no-store",
  });

  const payload = (await response.json()) as ApiSuccessResponse<CheckoutPaymentAvailability>;

  if (!response.ok || payload.success === false || !payload.data) {
    throw new CheckoutPaymentMethodsApiError(
      formatError(payload, "Unable to load payment methods."),
      response.status,
    );
  }

  return payload.data;
}

/** Warm payment-methods for the payment page while the shopper is still on checkout. */
export function prefetchCheckoutPaymentMethods(token?: string | null): void {
  const authToken = token ?? getCustomerApiToken();
  if (!authToken) {
    return;
  }

  const now = Date.now();
  if (
    prefetched &&
    prefetched.token === authToken &&
    prefetched.expiresAt > now
  ) {
    return;
  }

  const promise = loadCheckoutPaymentMethods(authToken).catch((error) => {
    if (prefetched?.promise === promise) {
      prefetched = null;
    }
    throw error;
  });

  prefetched = {
    token: authToken,
    expiresAt: now + PREFETCH_TTL_MS,
    promise,
  };
}

export async function fetchCheckoutPaymentMethods(
  token?: string | null,
): Promise<CheckoutPaymentAvailability> {
  const authToken = token ?? getCustomerApiToken();
  if (!authToken) {
    throw new CheckoutPaymentMethodsApiError("Sign in to load payment methods.", 401);
  }

  const now = Date.now();
  if (
    prefetched &&
    prefetched.token === authToken &&
    prefetched.expiresAt > now
  ) {
    return prefetched.promise;
  }

  const promise = loadCheckoutPaymentMethods(authToken).catch((error) => {
    if (prefetched?.promise === promise) {
      prefetched = null;
    }
    throw error;
  });

  prefetched = {
    token: authToken,
    expiresAt: now + PREFETCH_TTL_MS,
    promise,
  };

  return promise;
}

export function __clearCheckoutPaymentMethodsPrefetchForTests(): void {
  prefetched = null;
}
