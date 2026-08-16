import { DEFAULT_WEB_APP_BASE_URL, env, isLoopbackApiUrl } from '@/src/core/config/env';

/**
 * Canonical production web paths used by Resend auth emails and optional in-app handoffs.
 * Backend builds the same paths from FRONTEND_URL — mobile must not invent hosts.
 */
export type AuthWebPath = '/forgot-password' | '/reset-password' | '/verify-email';

export function resolveAuthWebBaseUrl(input?: {
  webAppBaseUrl?: string;
  isDev?: boolean;
}): string {
  const isDev =
    input?.isDev ??
    (typeof __DEV__ !== 'undefined' ? __DEV__ : process.env.NODE_ENV !== 'production');
  const candidate = (input?.webAppBaseUrl ?? env.webAppBaseUrl ?? DEFAULT_WEB_APP_BASE_URL)
    .trim()
    .replace(/\/$/, '');

  if (!candidate) {
    return DEFAULT_WEB_APP_BASE_URL;
  }

  if (!isDev && isLoopbackApiUrl(candidate)) {
    return DEFAULT_WEB_APP_BASE_URL;
  }

  try {
    const url = new URL(candidate);
    if (url.protocol !== 'https:' && !isDev) {
      return DEFAULT_WEB_APP_BASE_URL;
    }
    return `${url.origin}`;
  } catch {
    return DEFAULT_WEB_APP_BASE_URL;
  }
}

export function buildAuthWebUrl(
  path: AuthWebPath,
  input?: { webAppBaseUrl?: string; isDev?: boolean },
): string {
  return `${resolveAuthWebBaseUrl(input)}${path}`;
}
