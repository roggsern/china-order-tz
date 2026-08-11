import { ApiError, getSharedTransportErrorMessage } from '@/src/core/errors';

/**
 * User-facing order errors — never expose raw technical codes in UI copy.
 */
export function getOrderErrorMessage(error: unknown): string {
  const transport = getSharedTransportErrorMessage(error);
  if (transport) return transport;

  if (!(error instanceof ApiError)) {
    return 'Unable to load your orders. Please try again.';
  }

  switch (error.code) {
    case 'unauthenticated':
      return error.message || 'Please sign in to view your orders.';
    case 'validation_failed': {
      const first = Object.values(error.errors).flat()[0];
      return first || error.message || 'Please check your request and try again.';
    }
    case 'not_found':
      return error.message || 'This order was not found.';
    case 'business_rule_violated':
      return (
        error.message?.trim() ||
        'This order action is not allowed right now.'
      );
    case 'maintenance_mode':
      return error.message || 'The store is temporarily under maintenance.';
    case 'server_error':
      return error.message || 'Something went wrong loading your orders.';
    default:
      return error.message || 'Unable to load your orders. Please try again.';
  }
}

export function isOrderUnauthenticatedError(error: unknown): boolean {
  return error instanceof ApiError && error.isUnauthenticated;
}

export function isOrderCancellationRejected(error: unknown): boolean {
  return error instanceof ApiError && error.code === 'business_rule_violated';
}
