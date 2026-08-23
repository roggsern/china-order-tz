import { ApiError } from '@/src/core/errors';

/**
 * A completed session already produced an order. DELETE must not be treated as
 * a successful abandon, and must not trigger a replacement session.
 */
export function shouldCancelCheckoutSession(input: {
  sessionId?: string | null;
  sessionStatus?: string | null;
  orderId?: string | null;
}): boolean {
  const sessionId = input.sessionId?.trim();
  if (!sessionId) return false;
  if (input.orderId?.trim()) return false;
  const status = (input.sessionStatus ?? '').toLowerCase();
  if (status === 'completed') return false;
  return true;
}

export function isCheckoutSessionAlreadyGone(error: unknown): boolean {
  return error instanceof ApiError && (error.code === 'not_found' || error.status === 404);
}

export function isCompletedCheckoutSessionCancelError(error: unknown): boolean {
  if (!(error instanceof ApiError)) return false;
  if (error.code !== 'business_rule_violated' && error.status !== 422) return false;
  const fieldText = Object.values(error.errors).flat().join(' ');
  const haystack = `${error.message} ${fieldText}`.toLowerCase();
  return /completed checkout sessions cannot be cancelled/.test(haystack);
}
