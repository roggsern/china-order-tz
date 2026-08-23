import type {
  NmbBrowserReturnParams,
  PaymentMethodAvailability,
  PaymentMethodsAvailability,
  PaymentOrder,
  PaymentOrderSummary,
  PaymentTransaction,
  ReconcileNmbReturnInput,
  ReconcileNmbReturnPayload,
  StartPaymentPayload,
} from '../models/types';
import { env, NMB_SANDBOX_GATEWAY_HOST } from '@/src/core/config';

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value : null;
}

function moneyField(data: Record<string, unknown>, key: string): string | number | null {
  const value = data[key];
  if (typeof value === 'string' || typeof value === 'number') return value;
  return null;
}

function boolField(data: Record<string, unknown>, key: string): boolean {
  return data[key] === true;
}

export function mapPaymentMethod(raw: unknown): PaymentMethodAvailability | null {
  const data = asRecord(raw);
  const code = stringField(data, 'code');
  if (!code) return null;
  return {
    code,
    enabled: boolField(data, 'enabled'),
    available: boolField(data, 'available'),
    // Mobile currently starts NMB only. Do not expose Pay at Office as selectable.
    selectable: code === 'cash' ? false : boolField(data, 'selectable'),
  };
}

export function mapPaymentMethods(raw: unknown): PaymentMethodsAvailability {
  const data = asRecord(raw);
  const methodsRaw = Array.isArray(data.methods) ? data.methods : [];
  const enabledRaw = Array.isArray(data.enabled_methods) ? data.enabled_methods : [];

  return {
    defaultProvider: stringField(data, 'default_provider'),
    enabledMethods: enabledRaw.filter((item): item is string => typeof item === 'string'),
    methods: methodsRaw
      .map(mapPaymentMethod)
      .filter((item): item is PaymentMethodAvailability => item !== null),
  };
}

function mapPaymentOrderSummary(raw: unknown): PaymentOrderSummary | null {
  const data = asRecord(raw);
  const id = stringField(data, 'id');
  if (!id) return null;
  return {
    id,
    orderNumber: stringField(data, 'order_number'),
    status: stringField(data, 'status'),
    grandTotal: moneyField(data, 'grand_total'),
    currency: stringField(data, 'currency'),
  };
}

export function mapPaymentTransaction(raw: unknown): PaymentTransaction {
  const data = asRecord(raw);
  return {
    id: stringField(data, 'id') ?? '',
    orderId: stringField(data, 'order_id') ?? '',
    provider: stringField(data, 'provider'),
    providerReference: stringField(data, 'provider_reference'),
    merchantReference: stringField(data, 'merchant_reference'),
    currency: stringField(data, 'currency') ?? 'TZS',
    amount: moneyField(data, 'amount'),
    status: (stringField(data, 'status') ?? 'pending') as PaymentTransaction['status'],
    checkoutUrl: stringField(data, 'checkout_url'),
    successIndicator: stringField(data, 'success_indicator'),
    order: mapPaymentOrderSummary(data.order),
    initiatedAt: stringField(data, 'initiated_at'),
    completedAt: stringField(data, 'completed_at'),
  };
}

export function mapPaymentOrder(raw: unknown): PaymentOrder {
  const data = asRecord(raw);
  return {
    id: stringField(data, 'id') ?? '',
    orderNumber: stringField(data, 'order_number'),
    status: stringField(data, 'status'),
    currency: stringField(data, 'currency') ?? 'TZS',
    grandTotal: moneyField(data, 'grand_total'),
    checkoutSessionId: stringField(data, 'checkout_session_id'),
  };
}

export function buildStartPaymentPayload(provider?: string | null): StartPaymentPayload {
  const trimmed = provider?.trim();
  return trimmed ? { provider: trimmed } : {};
}

export function buildReconcileNmbPayload(
  input: ReconcileNmbReturnInput,
): ReconcileNmbReturnPayload {
  const payload: ReconcileNmbReturnPayload = {
    payment_transaction_id: input.paymentTransactionId,
    merchant_reference: input.merchantReference,
    success_indicator: input.successIndicator,
    result_indicator: input.resultIndicator,
  };
  if (input.orderId?.trim()) {
    payload.order_id = input.orderId.trim();
  }
  return payload;
}

/** Extract NMB return query params for server proof — never treat as local paid. */
export function extractNmbReturnParams(url: string): NmbBrowserReturnParams {
  const queryIndex = url.indexOf('?');
  const query = queryIndex >= 0 ? url.slice(queryIndex + 1).split('#')[0] : '';
  const params = new URLSearchParams(query);
  const get = (...keys: string[]) => {
    for (const key of keys) {
      const value = params.get(key);
      if (value && value.trim()) return value.trim();
    }
    return null;
  };

  return {
    resultIndicator: get('resultIndicator', 'result_indicator'),
    orderId: get('order_id', 'orderId'),
    merchantReference: get('merchant_reference', 'merchantReference'),
    paymentTransactionId: get('payment_transaction_id', 'paymentTransactionId'),
  };
}

export function canOpenCheckoutUrl(checkoutUrl: string | null | undefined): boolean {
  if (!checkoutUrl?.trim()) return false;
  try {
    const parsed = new URL(checkoutUrl);
    if (parsed.protocol !== 'https:') {
      return false;
    }
    const host = parsed.hostname.toLowerCase();
    if (!host) return false;

    // Suffix matches would otherwise allow sandbox MPGS hosts in production builds.
    if (!env.allowNmbSandboxCheckout && host === NMB_SANDBOX_GATEWAY_HOST) {
      return false;
    }

    if (env.paymentCheckoutAllowedHosts.includes(host)) {
      return true;
    }

    return env.paymentCheckoutAllowedHostSuffixes.some(
      (suffix) => suffix.startsWith('.') && host.endsWith(suffix),
    );
  } catch {
    return false;
  }
}

/**
 * Pure allowlist check for tests — mirrors {@link canOpenCheckoutUrl} host rules.
 */
export function isPaymentCheckoutHostAllowed(
  host: string,
  options: {
    allowSandbox: boolean;
    allowedHosts: string[];
    allowedSuffixes: string[];
  },
): boolean {
  const normalized = host.trim().toLowerCase();
  if (!normalized) return false;
  if (!options.allowSandbox && normalized === NMB_SANDBOX_GATEWAY_HOST) {
    return false;
  }
  if (options.allowedHosts.includes(normalized)) {
    return true;
  }
  return options.allowedSuffixes.some(
    (suffix) => suffix.startsWith('.') && normalized.endsWith(suffix),
  );
}

/**
 * True when NMB returned a gateway session without a redirect checkout_url.
 * Matches web Website Hosted Checkout (Checkout.js) eligibility.
 */
export function isNmbWebsiteHostedCheckout(transaction: {
  provider?: string | null;
  checkoutUrl?: string | null;
  providerReference?: string | null;
}): boolean {
  const provider = (transaction.provider ?? '').toLowerCase();
  return (
    provider === 'nmb' &&
    !transaction.checkoutUrl?.trim() &&
    Boolean(transaction.providerReference?.trim())
  );
}

export const UNSAFE_CHECKOUT_URL_MESSAGE =
  'Payment service is unavailable. Please try again.';


export function isTerminalPaymentStatus(status: string | null | undefined): boolean {
  return (
    status === 'successful' ||
    status === 'failed' ||
    status === 'cancelled' ||
    status === 'expired'
  );
}

export function isSuccessfulPaymentStatus(status: string | null | undefined): boolean {
  return status === 'successful';
}

/** Friendly label — does not invent payment success. */
export function paymentStatusLabel(status: string | null | undefined): string {
  switch (status) {
    case 'successful':
      return 'Paid';
    case 'processing':
      return 'Processing';
    case 'pending':
      return 'Pending';
    case 'failed':
      return 'Failed';
    case 'cancelled':
      return 'Cancelled';
    case 'expired':
      return 'Expired';
    default:
      return status ? status.replace(/_/g, ' ') : 'Unknown';
  }
}

export function formatPaymentMoney(
  value: string | number | null | undefined,
  currency = 'TZS',
): string {
  if (value == null) return '—';
  return `${currency} ${String(value)}`;
}
