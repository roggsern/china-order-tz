import { Linking } from 'react-native';
import { DEFAULT_WEB_APP_BASE_URL, env } from '@/src/core/config/env';

/**
 * Customer-facing storefront URLs opened from the mobile account hub.
 * Legal pages stay on the web origin — do not duplicate policy text in-app.
 */
export type AccountWebPath =
  | '/account'
  | '/privacy'
  | '/terms'
  | '/delete-account';

export function buildAccountWebUrl(path: AccountWebPath): string {
  const base = (env.webAppBaseUrl || DEFAULT_WEB_APP_BASE_URL).replace(/\/$/, '');
  return `${base}${path}`;
}

export async function openAccountWebPage(path: AccountWebPath): Promise<void> {
  const url = buildAccountWebUrl(path);
  const canOpen = await Linking.canOpenURL(url);
  if (!canOpen) {
    throw new Error('Unable to open the storefront page.');
  }
  await Linking.openURL(url);
}
