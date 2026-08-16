import Constants from 'expo-constants';

export type AdminEnv = {
  apiBaseUrl: string;
  appScheme: string;
};

export const PRODUCTION_API_BASE_URL = 'https://api.chinaordertz.com/api/v1';

export const MISSING_PRODUCTION_API_URL_MESSAGE =
  'EXPO_PUBLIC_API_BASE_URL (or app.json extra.apiBaseUrl) must be set to a non-localhost API for production builds.';

function readExtra(key: string): string | undefined {
  const extra = Constants.expoConfig?.extra as Record<string, unknown> | undefined;
  const value = extra?.[key];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : undefined;
}

function isDevRuntime(isDev?: boolean): boolean {
  return (
    isDev ??
    (typeof __DEV__ !== 'undefined' ? __DEV__ : process.env.NODE_ENV !== 'production')
  );
}

export function isLoopbackApiUrl(url: string): boolean {
  try {
    const host = new URL(url).hostname.toLowerCase();
    return (
      host === 'localhost' ||
      host === '127.0.0.1' ||
      host === '0.0.0.0' ||
      host === '10.0.2.2' ||
      host === '[::1]' ||
      host === '::1'
    );
  } catch {
    return true;
  }
}

/**
 * Resolve API base URL.
 * Development may fall back to localhost.
 * Production-like builds fail fast on missing/loopback URLs.
 */
export function resolveApiBaseUrl(input?: {
  fromProcess?: string | undefined;
  fromExtra?: string | undefined;
  isDev?: boolean;
}): string {
  const fromProcess = (input?.fromProcess ?? process.env.EXPO_PUBLIC_API_BASE_URL)?.trim();
  const fromExtra = (input?.fromExtra ?? readExtra('apiBaseUrl'))?.trim();
  const isDev = isDevRuntime(input?.isDev);

  const candidate = (fromProcess || fromExtra || '').replace(/\/$/, '');

  if (isDev) {
    return candidate || 'http://localhost:8000/api/v1';
  }

  if (!candidate || isLoopbackApiUrl(candidate)) {
    throw new Error(MISSING_PRODUCTION_API_URL_MESSAGE);
  }

  return candidate;
}

export const env: AdminEnv = {
  apiBaseUrl: resolveApiBaseUrl(),
  appScheme: Constants.expoConfig?.scheme?.toString() ?? 'chinaordertzadmin',
};
