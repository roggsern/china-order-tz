import { hasAdminPermission } from "@/lib/api/admin-me";

export class AdminPaymentConfigApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
    this.name = "AdminPaymentConfigApiError";
  }
}

export type PaymentEnabledMethods = {
  nmb: boolean;
  snippe: boolean;
  mpesa: boolean;
  card: boolean;
  cash: boolean;
  bank_transfer: boolean;
};

export const PAYMENT_METHOD_ORDER: (keyof PaymentEnabledMethods)[] = [
  "nmb",
  "snippe",
  "mpesa",
  "card",
  "cash",
  "bank_transfer",
];

export function defaultPaymentEnabledMethods(): PaymentEnabledMethods {
  return {
    nmb: true,
    snippe: false,
    mpesa: false,
    card: false,
    cash: false,
    bank_transfer: false,
  };
}

export function mergePaymentEnabledMethods(
  incoming?: Partial<PaymentEnabledMethods> | null,
  current: PaymentEnabledMethods = defaultPaymentEnabledMethods(),
): PaymentEnabledMethods {
  return {
    ...defaultPaymentEnabledMethods(),
    ...current,
    ...(incoming ?? {}),
  };
}

/** Complete enabled_methods payload expected by the payments config API. */
export function paymentEnabledMethodsPayload(
  methods: PaymentEnabledMethods,
): PaymentEnabledMethods {
  return {
    nmb: Boolean(methods.nmb),
    snippe: Boolean(methods.snippe),
    mpesa: Boolean(methods.mpesa),
    card: Boolean(methods.card),
    cash: Boolean(methods.cash),
    bank_transfer: Boolean(methods.bank_transfer),
  };
}

export function isPaymentMethodEnabled(
  methods: PaymentEnabledMethods,
  method: keyof PaymentEnabledMethods,
): boolean {
  return Boolean(methods[method]);
}

export type AdminPaymentConfig = {
  default_provider: string;
  enabled_methods: PaymentEnabledMethods;
  provider_status?: Record<string, { enabled: boolean; available: boolean }>;
  managed_methods?: string[];
};

export type UpdateAdminPaymentConfigInput = {
  default_provider?: string;
  enabled_methods?: Partial<PaymentEnabledMethods>;
};

async function parseJson<T>(response: Response): Promise<T> {
  try {
    return (await response.json()) as T;
  } catch {
    return {} as T;
  }
}

function throwFromPayload(
  response: Response,
  payload: { message?: string; errors?: Record<string, string[]> },
  fallback: string,
): never {
  const firstError = payload.errors ? Object.values(payload.errors).flat()[0] : undefined;
  throw new AdminPaymentConfigApiError(
    firstError?.trim() || payload.message?.trim() || fallback,
    response.status,
  );
}

export function canViewPaymentConfig(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "payments.config.view");
}

export function canManagePaymentConfig(permissions: string[] | undefined): boolean {
  return hasAdminPermission(permissions, "payments.config.manage");
}

export async function fetchAdminPaymentConfig(): Promise<AdminPaymentConfig> {
  const response = await fetch("/api/admin/payments/config", {
    method: "GET",
    credentials: "include",
    cache: "no-store",
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminPaymentConfig;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to load payment configuration.");
  }

  if (!payload.data) {
    throw new AdminPaymentConfigApiError("Invalid payment config response.", response.status);
  }

  return payload.data;
}

export async function updateAdminPaymentConfig(
  input: UpdateAdminPaymentConfigInput,
): Promise<AdminPaymentConfig> {
  const response = await fetch("/api/admin/payments/config", {
    method: "PUT",
    credentials: "include",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(input),
  });
  const payload = await parseJson<{
    success?: boolean;
    message?: string;
    data?: AdminPaymentConfig;
    errors?: Record<string, string[]>;
  }>(response);

  if (!response.ok) {
    throwFromPayload(response, payload, "Unable to update payment configuration.");
  }

  if (!payload.data) {
    throw new AdminPaymentConfigApiError("Invalid payment config response.", response.status);
  }

  return payload.data;
}

export const PAYMENT_METHOD_LABELS: Record<keyof PaymentEnabledMethods, string> = {
  nmb: "NMB",
  snippe: "Mobile Money (Snippe)",
  mpesa: "M-Pesa",
  card: "Card",
  cash: "Pay at Office",
  bank_transfer: "Bank transfer",
};
