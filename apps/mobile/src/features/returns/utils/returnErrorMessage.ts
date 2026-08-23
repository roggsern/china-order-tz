import { ApiError, getSharedTransportErrorMessage } from '@/src/core/errors';

export function getReturnErrorMessage(error: unknown): string {
  const transport = getSharedTransportErrorMessage(error);
  if (transport) return transport;

  if (!(error instanceof ApiError)) {
    return 'Unable to load returns. Please try again.';
  }

  switch (error.code) {
    case 'unauthenticated':
      return error.message || 'Please sign in to manage returns.';
    case 'validation_failed': {
      const first = Object.values(error.errors).flat()[0];
      return first || error.message || 'Please check your return request and try again.';
    }
    case 'not_found':
      return error.message || 'This return request was not found.';
    case 'business_rule_violated':
      return error.message?.trim() || 'This order is not eligible for return right now.';
    case 'maintenance_mode':
      return error.message || 'The store is temporarily under maintenance.';
    case 'server_error':
      return error.message || 'Something went wrong with this return request.';
    default:
      return error.message || 'Unable to load returns. Please try again.';
  }
}

export function isReturnUnauthenticatedError(error: unknown): boolean {
  return error instanceof ApiError && error.isUnauthenticated;
}
