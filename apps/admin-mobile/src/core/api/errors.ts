export const API_ERROR_CODES = [
  'unauthenticated',
  'invalid_credentials',
  'account_disabled',
  'validation_failed',
  'business_rule_violated',
  'not_found',
  'forbidden',
  'maintenance_mode',
  'server_error',
  'network_error',
  'timeout',
] as const;

export type ApiErrorCode = (typeof API_ERROR_CODES)[number] | (string & {});

export type ApiFieldErrors = Record<string, string[]>;

export type ApiErrorPayload = {
  success?: boolean;
  code?: string;
  message?: string;
  errors?: ApiFieldErrors;
  request_id?: string;
  [key: string]: unknown;
};

export class ApiError extends Error {
  readonly name = 'ApiError';
  readonly status: number;
  readonly code: ApiErrorCode;
  readonly errors: ApiFieldErrors;
  readonly requestId: string | null;
  readonly raw: ApiErrorPayload | null;

  constructor(params: {
    message: string;
    status: number;
    code: ApiErrorCode;
    errors?: ApiFieldErrors;
    requestId?: string | null;
    raw?: ApiErrorPayload | null;
  }) {
    super(params.message);
    this.status = params.status;
    this.code = params.code;
    this.errors = params.errors ?? {};
    this.requestId = params.requestId ?? null;
    this.raw = params.raw ?? null;
  }

  get isUnauthenticated(): boolean {
    return this.code === 'unauthenticated' || this.status === 401;
  }

  get isForbidden(): boolean {
    return this.code === 'forbidden' || this.status === 403;
  }

  get isNetworkFailure(): boolean {
    return this.code === 'network_error' || this.code === 'timeout';
  }
}

export function isApiErrorCode(value: unknown): value is ApiErrorCode {
  return typeof value === 'string' && value.length > 0;
}

function asFieldErrors(value: unknown): ApiFieldErrors {
  if (!value || typeof value !== 'object') return {};
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
    default:
      return 'server_error';
  }
}

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
    `Request failed (${status})`;

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
    if (name === 'TimeoutError' || name === 'AbortError') return true;
  }
  const rawMessage = error instanceof Error ? error.message : String(error ?? '');
  return rawMessage.toLowerCase().includes('timeout') || rawMessage.toLowerCase().includes('abort');
}

export function isTimeoutAbortReason(reason: unknown): boolean {
  return isTimeoutLike(reason);
}

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
