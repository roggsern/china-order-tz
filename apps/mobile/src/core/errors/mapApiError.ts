import {
  ApiError,
  type ApiErrorPayload,
  type ApiFieldErrors,
  isApiErrorCode,
  isKnownApiErrorCode,
} from './apiError';

function asFieldErrors(value: unknown): ApiFieldErrors {
  if (!value || typeof value !== 'object') {
    return {};
  }

  const out: ApiFieldErrors = {};
  for (const [key, messages] of Object.entries(value as Record<string, unknown>)) {
    if (Array.isArray(messages) && messages.every((m) => typeof m === 'string')) {
      out[key] = messages;
    } else if (typeof messages === 'string') {
      out[key] = [messages];
    }
  }
  return out;
}

function inferCodeFromStatus(status: number): string {
  switch (status) {
    case 401:
      return 'unauthenticated';
    case 403:
      return 'forbidden';
    case 404:
      return 'not_found';
    case 422:
      return 'validation_failed';
    case 503:
      return 'maintenance_mode';
    case 500:
      return 'server_error';
    default:
      return 'server_error';
  }
}

/**
 * Map a failed HTTP JSON body (Contract v1) into a typed ApiError.
 */
export function mapApiError(status: number, body: unknown, fallbackMessage?: string): ApiError {
  const payload =
    body && typeof body === 'object' ? (body as ApiErrorPayload) : ({} as ApiErrorPayload);

  const codeRaw = typeof payload.code === 'string' ? payload.code : inferCodeFromStatus(status);
  const code = isApiErrorCode(codeRaw) ? codeRaw : inferCodeFromStatus(status);

  const message =
    (typeof payload.message === 'string' && payload.message.trim() !== ''
      ? payload.message
      : null) ??
    fallbackMessage ??
    (isKnownApiErrorCode(code) ? `Request failed (${code})` : `Request failed (${status})`);

  return new ApiError({
    message,
    status,
    code,
    errors: asFieldErrors(payload.errors),
    requestId: typeof payload.request_id === 'string' ? payload.request_id : null,
    raw: payload,
  });
}

function isTimeoutLike(error: unknown): boolean {
  if (error && typeof error === 'object') {
    const name = 'name' in error ? String((error as { name?: unknown }).name) : '';
    if (name === 'TimeoutError' || name === 'AbortError') {
      // AbortError from our timeout controller includes timed-out message.
      const message =
        'message' in error ? String((error as { message?: unknown }).message) : '';
      if (name === 'TimeoutError' || isTimeoutMessage(message)) {
        return true;
      }
      // External abort without timeout wording → treat as network/abort timeout UX.
      if (name === 'AbortError') return true;
    }
  }
  const rawMessage = error instanceof Error ? error.message : String(error ?? '');
  return isTimeoutMessage(rawMessage);
}

function isTimeoutMessage(message: string): boolean {
  const lower = message.toLowerCase();
  return (
    lower.includes('timeout') ||
    lower.includes('timed out') ||
    lower.includes('aborted') ||
    lower.includes('abort')
  );
}

/**
 * Classify transport failures — never expose stacks; UI maps codes to copy.
 */
export function mapNetworkError(error: unknown): ApiError {
  if (isTimeoutLike(error)) {
    return new ApiError({
      message: 'Request timed out. Please try again.',
      status: 0,
      code: 'timeout',
    });
  }

  return new ApiError({
    message: 'No internet connection. Check your connection and try again.',
    status: 0,
    code: 'network_error',
  });
}
