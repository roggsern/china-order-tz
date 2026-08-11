import { ApiError } from '@/src/core/errors';

/**
 * Human-readable auth error for MVP screens.
 * Relies on Contract v1 `code` + `message` / `errors`.
 */
export function getAuthErrorMessage(error: unknown): string {
  if (!(error instanceof ApiError)) {
    return 'Something went wrong. Please try again.';
  }

  switch (error.code) {
    case 'invalid_credentials':
      return error.message || 'Invalid email or password.';
    case 'account_disabled':
      return error.message || 'This account has been disabled. Contact support.';
    case 'unauthenticated':
      return error.message || 'Please sign in again.';
    case 'validation_failed': {
      const firstField = Object.values(error.errors)[0]?.[0];
      return firstField || error.message || 'Please check your details.';
    }
    default:
      return error.message || 'Something went wrong. Please try again.';
  }
}

export function getAuthFieldErrors(error: unknown): Record<string, string> {
  if (!(error instanceof ApiError) || error.code !== 'validation_failed') {
    return {};
  }

  const out: Record<string, string> = {};
  for (const [field, messages] of Object.entries(error.errors)) {
    if (messages[0]) {
      out[field] = messages[0];
    }
  }
  return out;
}
