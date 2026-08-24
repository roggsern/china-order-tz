import { ApiError, getSharedTransportErrorMessage } from '@/src/core/errors';

export function getHomepageErrorMessage(error: unknown): string {
  const transport = getSharedTransportErrorMessage(error);
  if (transport) return transport;

  if (!(error instanceof ApiError)) {
    return 'Unable to load home. Please try again.';
  }

  switch (error.code) {
    case 'maintenance_mode':
      return error.message || 'The store is temporarily under maintenance.';
    case 'unauthenticated':
      return error.message || 'Please sign in again to continue.';
    case 'server_error':
      return error.message || 'Something went wrong loading home.';
    default:
      return error.message || 'Unable to load home. Please try again.';
  }
}
