import type { AppStateStatus } from 'react-native';

/**
 * True only when returning from background/inactive — not the first mount.
 */
export function shouldRunForegroundCommerceRefresh(
  previous: AppStateStatus | null,
  next: AppStateStatus,
): boolean {
  return previous != null && previous !== 'active' && next === 'active';
}

export function shouldRefreshActivePaymentOnResume(input: {
  viewKind: string | null | undefined;
  transactionId: string | null | undefined;
}): boolean {
  if (!input.transactionId?.trim()) return false;
  return input.viewKind === 'recovery' || input.viewKind === 'processing';
}

export const FOREGROUND_COMMERCE_QUERY_PREFIXES = [
  ['cart', 'current'],
  ['orders'],
  ['checkout'],
  ['payments'],
  ['account', 'notifications'],
  ['notifications'],
] as const;
