import { env } from '@/src/core/config';
import { secureTokenStorage } from '@/src/core/storage';

import {
  ApiError,
  isTimeoutAbortReason,
  mapApiError,
  mapNetworkError,
} from './errors';
import type { ApiRequestOptions, ApiSuccessEnvelope } from './types';

type TokenReader = () => Promise<string | null>;
type UnauthorizedHandler = () => void | Promise<void>;

export const DEFAULT_API_REQUEST_TIMEOUT_MS = 30_000;

let tokenReader: TokenReader = () => secureTokenStorage.readToken();
let unauthorizedHandler: UnauthorizedHandler | null = null;

export function setApiTokenReader(reader: TokenReader): void {
  tokenReader = reader;
}

export function setUnauthorizedHandler(handler: UnauthorizedHandler | null): void {
  unauthorizedHandler = handler;
}

function buildUrl(path: string, query?: ApiRequestOptions['query']): string {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  const url = new URL(`${env.apiBaseUrl}${normalizedPath}`);

  if (query) {
    for (const [key, value] of Object.entries(query)) {
      if (value === undefined || value === null || value === '') continue;
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
      if (external) external.removeEventListener('abort', onExternalAbort);
    },
  };
}

async function handleUnauthorized(): Promise<void> {
  await secureTokenStorage.clearToken();
  if (unauthorizedHandler) {
    await unauthorizedHandler();
  }
}

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

  const token = options.token === undefined ? await tokenReader() : options.token;
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
    if (signal.aborted && isTimeoutAbortReason(signal.reason)) {
      throw mapNetworkError(signal.reason);
    }
    throw mapNetworkError(error);
  } finally {
    cleanup();
  }

  const body = await parseJsonSafe(response);

  if (!response.ok) {
    const apiError = mapApiError(response.status, body);
    if (apiError.isUnauthenticated) {
      await handleUnauthorized();
    }
    throw apiError;
  }

  if (body && typeof body === 'object' && (body as { success?: unknown }).success === false) {
    const apiError = mapApiError(response.status, body);
    if (apiError.isUnauthenticated) {
      await handleUnauthorized();
    }
    throw apiError;
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
};
