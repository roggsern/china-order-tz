import { env } from '@/src/core/config';
import { ApiError, mapApiError, mapNetworkError } from '@/src/core/errors';
import { secureTokenStorage } from '@/src/core/storage';
import type { ApiRequestOptions, ApiSuccessEnvelope } from './types';

type TokenReader = () => Promise<string | null>;

/** Shared deadline for API fetch only — not NMB hosted-browser sessions. */
export const DEFAULT_API_REQUEST_TIMEOUT_MS = 30_000;

let tokenReader: TokenReader = () => secureTokenStorage.readToken();

/** Test / DI hook — production uses SecureStore. */
export function setApiTokenReader(reader: TokenReader): void {
  tokenReader = reader;
}

function buildUrl(path: string, query?: ApiRequestOptions['query']): string {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  const url = new URL(`${env.apiBaseUrl}${normalizedPath}`);

  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value === undefined || value === null) continue;
      url.searchParams.set(key, String(value));
    }
  }

  return url.toString();
}

async function parseJsonSafe(response: Response): Promise<unknown> {
  const text = await response.text();
  if (!text) return null;
  try {
    return JSON.parse(text) as unknown;
  } catch {
    return { message: text };
  }
}

/**
 * Combine optional caller signal with a request timeout.
 * Timeout applies to HTTP API only — not WebBrowser payment sessions.
 */
export function createApiRequestSignal(
  timeoutMs: number,
  external?: AbortSignal,
): { signal: AbortSignal; cleanup: () => void } {
  const controller = new AbortController();
  const timer = setTimeout(() => {
    controller.abort(Object.assign(new Error('Request timed out'), { name: 'TimeoutError' }));
  }, timeoutMs);

  const onExternalAbort = () => {
    controller.abort(external?.reason);
  };

  if (external) {
    if (external.aborted) {
      controller.abort(external.reason);
    } else {
      external.addEventListener('abort', onExternalAbort, { once: true });
    }
  }

  return {
    signal: controller.signal,
    cleanup: () => {
      clearTimeout(timer);
      if (external) {
        external.removeEventListener('abort', onExternalAbort);
      }
    },
  };
}

/**
 * Shared Laravel API client (Contract v1).
 */
export async function apiRequest<T = unknown>(
  options: ApiRequestOptions,
): Promise<ApiSuccessEnvelope<T>> {
  const method = options.method ?? 'GET';
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...(options.headers ?? {}),
  };

  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
  }

  const token =
    options.token === undefined ? await tokenReader() : options.token;
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  const timeoutMs = options.timeoutMs ?? DEFAULT_API_REQUEST_TIMEOUT_MS;
  const { signal, cleanup } = createApiRequestSignal(timeoutMs, options.signal);

  let response: Response;
  try {
    response = await fetch(buildUrl(options.path, options.query), {
      method,
      headers,
      body: options.body === undefined ? undefined : JSON.stringify(options.body),
      signal,
    });
  } catch (error) {
    throw mapNetworkError(error);
  } finally {
    cleanup();
  }

  const body = await parseJsonSafe(response);

  if (!response.ok) {
    throw mapApiError(response.status, body);
  }

  if (body && typeof body === 'object' && (body as { success?: unknown }).success === false) {
    throw mapApiError(response.status, body);
  }

  const envelope = (body ?? { success: true, data: null }) as ApiSuccessEnvelope<T>;
  if (envelope.success !== true) {
    throw new ApiError({
      message: 'Unexpected API response shape',
      status: response.status,
      code: 'server_error',
      raw: body && typeof body === 'object' ? (body as never) : null,
    });
  }

  return envelope;
}

export const apiClient = {
  get: <T = unknown>(path: string, query?: ApiRequestOptions['query'], token?: string | null) =>
    apiRequest<T>({ method: 'GET', path, query, token }),
  post: <T = unknown>(path: string, body?: unknown, token?: string | null) =>
    apiRequest<T>({ method: 'POST', path, body, token }),
  put: <T = unknown>(path: string, body?: unknown, token?: string | null) =>
    apiRequest<T>({ method: 'PUT', path, body, token }),
  patch: <T = unknown>(path: string, body?: unknown, token?: string | null) =>
    apiRequest<T>({ method: 'PATCH', path, body, token }),
  delete: <T = unknown>(path: string, token?: string | null) =>
    apiRequest<T>({ method: 'DELETE', path, token }),
};
