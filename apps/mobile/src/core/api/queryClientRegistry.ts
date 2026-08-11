import type { QueryClient } from '@tanstack/react-query';

/**
 * Holds the app QueryClient so auth/logout can clear user caches outside React.
 */
let registeredQueryClient: QueryClient | null = null;

export function registerAppQueryClient(client: QueryClient): void {
  registeredQueryClient = client;
}

export function getAppQueryClient(): QueryClient | null {
  return registeredQueryClient;
}

/** User-specific query roots — keep public CMS/catalog/search caches. */
export const USER_SENSITIVE_QUERY_ROOTS = [
  'cart',
  'orders',
  'checkout',
  'payments',
  'profile',
  'account',
] as const;

/**
 * Remove authenticated commerce caches so the next account cannot see prior data.
 */
export function clearUserSensitiveQueryCaches(
  client: QueryClient | null = getAppQueryClient(),
): void {
  if (!client) return;
  for (const root of USER_SENSITIVE_QUERY_ROOTS) {
    client.removeQueries({ queryKey: [root] });
  }
}
