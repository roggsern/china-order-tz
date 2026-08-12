import { env } from '@/src/core/config/env';

/**
 * Resolve order snapshot / product media paths to an absolute HTTPS URL.
 * Mirrors web `resolveImageUrl` storage rules used by My Orders.
 *
 * Production-shaped payloads often return relative Laravel paths like:
 * - `/storage/products/blouse.jpg`
 * - `storage/products/blouse.jpg`
 * - `products/blouse.jpg`
 * - `demo-products/phone.jpg`
 *
 * Absolute http(s) URLs pass through unchanged.
 * Empty / unresolvable values return null (UI placeholder — never invent media).
 */
export function resolveApiOrigin(apiBaseUrl: string = env.apiBaseUrl): string {
  const trimmed = apiBaseUrl.trim().replace(/\/$/, '');
  if (!trimmed) return '';
  try {
    const url = new URL(trimmed);
    // Strip /api/v1 (or /api) so storage lives on the Laravel public origin.
    url.pathname = url.pathname.replace(/\/api(?:\/v\d+)?\/?$/, '') || '/';
    if (url.pathname === '/') {
      return `${url.protocol}//${url.host}`;
    }
    return `${url.protocol}//${url.host}${url.pathname.replace(/\/$/, '')}`;
  } catch {
    return trimmed.replace(/\/api(?:\/v\d+)?\/?$/, '');
  }
}

function stripStoragePrefix(value: string): string {
  return value.replace(/^\/+/, '').replace(/^storage\//, '');
}

function isLaravelStoragePath(value: string): boolean {
  return (
    value.startsWith('/storage/') ||
    value.startsWith('storage/') ||
    value.startsWith('products/') ||
    value.startsWith('demo-products/')
  );
}

export function resolveOrderMediaUrl(
  raw: string | null | undefined,
  apiBaseUrl: string = env.apiBaseUrl,
): string | null {
  if (typeof raw !== 'string') return null;
  const trimmed = raw.trim();
  if (!trimmed) return null;

  if (
    trimmed.startsWith('http://') ||
    trimmed.startsWith('https://') ||
    trimmed.startsWith('data:') ||
    trimmed.startsWith('blob:')
  ) {
    return trimmed;
  }

  const origin = resolveApiOrigin(apiBaseUrl);
  if (!origin) return null;

  if (isLaravelStoragePath(trimmed)) {
    return `${origin}/storage/${stripStoragePrefix(trimmed)}`;
  }

  // Absolute-path relative to API host (rare but seen in snapshots).
  if (trimmed.startsWith('/')) {
    return `${origin}${trimmed}`;
  }

  return null;
}
