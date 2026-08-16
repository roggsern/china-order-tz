/**
 * Account capability routing — native when authenticated APIs exist;
 * website handoff only for genuine contract gaps.
 */
export type AccountCapabilityId =
  | 'profile'
  | 'addresses'
  | 'wishlist'
  | 'security_password'
  | 'notifications'
  | 'support'
  | 'settings'
  | 'privacy'
  | 'terms'
  | 'logout';

export type AccountCapabilityDecision = 'native' | 'website' | 'action';

export type AccountCapability = {
  id: AccountCapabilityId;
  label: string;
  apiExists: boolean;
  decision: AccountCapabilityDecision;
  /** Expo route when decision is native. */
  nativeHref?: string;
  /** Website path when decision is website. */
  webPath?: '/account' | '/privacy' | '/terms';
  reason: string;
};

export const ACCOUNT_CAPABILITIES: AccountCapability[] = [
  {
    id: 'profile',
    label: 'Profile',
    apiExists: true,
    decision: 'native',
    nativeHref: '/(app)/account/profile',
    reason: 'GET|PATCH /profile',
  },
  {
    id: 'addresses',
    label: 'Addresses',
    apiExists: true,
    decision: 'native',
    nativeHref: '/(app)/account/addresses',
    reason: 'Existing /account/addresses APIs',
  },
  {
    id: 'wishlist',
    label: 'Wishlist',
    apiExists: true,
    decision: 'native',
    nativeHref: '/(app)/account/wishlist',
    reason: 'Existing wishlist APIs',
  },
  {
    id: 'security_password',
    label: 'Change password',
    apiExists: true,
    decision: 'native',
    nativeHref: '/(app)/account/change-password',
    reason: 'POST /account/change-password',
  },
  {
    id: 'notifications',
    label: 'Notifications',
    apiExists: true,
    decision: 'native',
    nativeHref: '/(app)/account/notifications',
    reason: 'GET/PATCH /notifications*',
  },
  {
    id: 'support',
    label: 'Support',
    apiExists: true,
    decision: 'native',
    nativeHref: '/(app)/account/support',
    reason: 'GET/POST /account/support/tickets*',
  },
  {
    id: 'settings',
    label: 'Settings',
    apiExists: false,
    decision: 'website',
    webPath: '/account',
    reason:
      'No dedicated mobile settings/preferences API (notification prefs, email change flows remain on web)',
  },
  {
    id: 'privacy',
    label: 'Privacy Policy',
    apiExists: false,
    decision: 'website',
    webPath: '/privacy',
    reason: 'Canonical public policy on chinaordertz.com/privacy',
  },
  {
    id: 'terms',
    label: 'Terms of Service',
    apiExists: false,
    decision: 'website',
    webPath: '/terms',
    reason: 'Canonical public terms on chinaordertz.com/terms',
  },
  {
    id: 'logout',
    label: 'Log out',
    apiExists: true,
    decision: 'action',
    reason: 'POST /logout + clearSession',
  },
];

export function resolveAccountCapability(
  id: AccountCapabilityId,
): AccountCapability {
  const found = ACCOUNT_CAPABILITIES.find((row) => row.id === id);
  if (!found) {
    throw new Error(`Unknown account capability: ${id}`);
  }
  return found;
}

export function listWebsiteAccountHandoffs(): AccountCapability[] {
  return ACCOUNT_CAPABILITIES.filter((row) => row.decision === 'website');
}

export function listNativeAccountCapabilities(): AccountCapability[] {
  return ACCOUNT_CAPABILITIES.filter((row) => row.decision === 'native');
}
