import { ApiError } from './apiError';

export const NETWORK_OFFLINE_MESSAGE =
  'No internet connection. Check your connection and try again.';

export const NETWORK_TIMEOUT_MESSAGE =
  'Request timed out. Please try again.';

export const GENERIC_SERVER_MESSAGE =
  'Something went wrong. Please try again.';

/**
 * Shared customer-facing transport / server fallbacks.
 * Returns null when the feature mapper should use its own domain copy.
 */
export function getSharedTransportErrorMessage(error: unknown): string | null {
  if (!(error instanceof ApiError)) {
    return null;
  }

  if (error.code === 'network_error') {
    return NETWORK_OFFLINE_MESSAGE;
  }
  if (error.code === 'timeout') {
    return NETWORK_TIMEOUT_MESSAGE;
  }
  // Legacy status-0 server_error (pre-Batch-3) treated as offline.
  if (error.code === 'server_error' && error.status === 0) {
    return NETWORK_OFFLINE_MESSAGE;
  }
  if (error.code === 'server_error') {
    return GENERIC_SERVER_MESSAGE;
  }
  return null;
}
