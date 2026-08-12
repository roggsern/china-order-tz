import { Linking } from 'react-native';
import { env } from '@/src/core/config/env';

/**
 * Customer account deep links on the merchant web storefront.
 * Used only for capabilities without a safe authenticated mobile API.
 */
export type AccountWebPath = '/account';

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
