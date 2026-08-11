import { ApiError, getSharedTransportErrorMessage } from '@/src/core/errors';

export function getSearchErrorMessage(error: unknown): string {
  const transport = getSharedTransportErrorMessage(error);
  if (transport) return transport;

  if (!(error instanceof ApiError)) {
    return 'Unable to search. Please try again.';
  }

  switch (error.code) {
    case 'maintenance_mode':
      return error.message || 'The store is temporarily under maintenance.';
    case 'validation_failed':
      return error.message || 'Please check your search and try again.';
    case 'unauthenticated':
      return error.message || 'Please sign in again to continue.';
    case 'server_error':
      return error.message || 'Something went wrong while searching.';
    default:
      return error.message || 'Unable to search. Please try again.';
  }
}
