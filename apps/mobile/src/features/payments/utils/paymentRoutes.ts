/**
 * Payment route builders — preserve order/session/transaction query through auth.
 */

export type PaymentRouteParams = {
  orderId?: string | null;
  checkoutSessionId?: string | null;
  paymentTransactionId?: string | null;
};

function appendParam(
  parts: string[],
  key: string,
  value: string | null | undefined,
): void {
  const trimmed = typeof value === 'string' ? value.trim() : '';
  if (!trimmed) return;
  parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(trimmed)}`);
}

/** In-app payment href with continuation query params. */
export function buildPaymentHref(params: PaymentRouteParams = {}): string {
  const query: string[] = [];
  appendParam(query, 'orderId', params.orderId);
  appendParam(query, 'checkoutSessionId', params.checkoutSessionId);
  appendParam(query, 'paymentTransactionId', params.paymentTransactionId);
  if (query.length === 0) {
    return '/(app)/payment';
  }
  return `/(app)/payment?${query.join('&')}`;
}

/** Parse payment continuation params from a path or full returnTo string. */
export function parsePaymentHrefParams(
  href: string | null | undefined,
): PaymentRouteParams {
  if (!href?.trim()) return {};
  const qIndex = href.indexOf('?');
  if (qIndex < 0) return {};
  const params = new URLSearchParams(href.slice(qIndex + 1));
  return {
    orderId: params.get('orderId'),
    checkoutSessionId: params.get('checkoutSessionId'),
    paymentTransactionId: params.get('paymentTransactionId'),
  };
}
