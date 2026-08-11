import { Linking } from 'react-native';
import { env } from '@/src/core/config/env';

/**
 * Customer account deep links on the merchant web storefront.
 * Built only from configured `webAppBaseUrl` — no invented contact details.
 */
export type AccountWebPath =
  | '/account'
  | '/account/addresses'
  | '/account/security'
  | '/account/support'
  | '/account/notifications';

export function buildAccountWebUrl(path: AccountWebPath): string {
  const base = env.webAppBaseUrl.replace(/\/$/, '');
  return `${base}${path}`;
}

export async function openAccountWebPage(path: AccountWebPath): Promise<void> {
  const url = buildAccountWebUrl(path);
  const canOpen = await Linking.canOpenURL(url);
  if (!canOpen) {
    throw new Error('Unable to open the storefront account page.');
  }
  await Linking.openURL(url);
}
