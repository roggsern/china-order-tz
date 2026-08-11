import { ApiError, getSharedTransportErrorMessage } from '@/src/core/errors';

/**
 * User-facing cart errors — never expose raw technical codes in UI copy.
 */
export function getCartErrorMessage(error: unknown): string {
  const transport = getSharedTransportErrorMessage(error);
  if (transport) return transport;

  if (!(error instanceof ApiError)) {
    return 'Unable to update your cart. Please try again.';
  }

  switch (error.code) {
    case 'unauthenticated':
      return error.message || 'Please sign in to view your cart.';
    case 'business_rule_violated': {
      const message = error.message?.trim() || '';
      if (/mix|different|channel|journey|CHINA|TZ_LOCAL|commerce/i.test(message)) {
        return 'Your cart cannot contain products from different journeys.';
      }
      return (
        message ||
        'This cart action is not allowed right now.'
      );
    }
    case 'validation_failed':
      return error.message || 'Please check quantity and try again.';
    case 'not_found':
      return error.message || 'This cart item is no longer available.';
    case 'maintenance_mode':
      return error.message || 'The store is temporarily under maintenance.';
    case 'server_error':
      return error.message || 'Something went wrong updating your cart.';
    default:
      return error.message || 'Unable to update your cart. Please try again.';
  }
}

export function isCartUnauthenticatedError(error: unknown): boolean {
  return error instanceof ApiError && error.isUnauthenticated;
}
