/**
 * Contract v1 API error codes recognized by the mobile client.
 * Keep values identical to Laravel Contract v1 (`docs/mobile-api-contract-v1.md`).
 * `network_error` / `timeout` are client-side transport classifications (status 0).
 */
export const API_ERROR_CODES = [
  'unauthenticated',
  'invalid_credentials',
  'account_disabled',
  'validation_failed',
  'business_rule_violated',
  'not_found',
  'payment_failed',
  'feature_disabled',
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

  get isNetworkFailure(): boolean {
    return this.code === 'network_error' || this.code === 'timeout';
  }
}

export function isApiErrorCode(value: unknown): value is ApiErrorCode {
  return typeof value === 'string' && value.length > 0;
}

export function isKnownApiErrorCode(value: string): boolean {
  return (API_ERROR_CODES as readonly string[]).includes(value);
}
