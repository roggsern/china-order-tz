import { ApiError } from '@/src/core/errors';

const NON_RETRYABLE_CODES = new Set([
  'unauthenticated',
  'invalid_credentials',
  'account_disabled',
  'forbidden',
  'not_found',
  'validation_failed',
  'business_rule_violated',
  'feature_disabled',
  'payment_failed',
]);

const TRANSIENT_READ_CODES = new Set([
  'timeout',
  'network_error',
  'server_error',
  'maintenance_mode',
]);

export const MAX_TRANSIENT_READ_RETRIES = 1;

/**
 * Bounded GET/read retry. Never retries 401 or client/business errors.
 * Mutations must stay at retry: 0.
 */
export function shouldRetryTransientRead(
  error: unknown,
  failureCount: number,
): boolean {
  if (failureCount >= MAX_TRANSIENT_READ_RETRIES) return false;
  if (!(error instanceof ApiError)) return true;
  if (error.isUnauthenticated) return false;
  if (NON_RETRYABLE_CODES.has(error.code)) return false;
  return TRANSIENT_READ_CODES.has(error.code) || error.status >= 500;
}

export function shouldRetryMutation(): boolean {
  return false;
}
