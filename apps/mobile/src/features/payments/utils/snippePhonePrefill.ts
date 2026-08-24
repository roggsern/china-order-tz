import type { PayNowView } from './payNowRecovery';
import { formatSnippePhoneForInput } from './snippePhone';

/**
 * Session input only. Profile/account phone is an initial suggestion, never a lock.
 * Reuses Wave 1 Snippe normalization; does not persist a payment-phone store.
 */
export function resolveSnippePhonePrefill(input: {
  profilePhone?: string | null;
  currentValue: string;
  editedInSession: boolean;
}): string {
  if (input.editedInSession) {
    return input.currentValue;
  }

  return formatSnippePhoneForInput(input.profilePhone ?? '') ?? '';
}

export function isSnippePhoneEntryVisible(input: {
  viewKind: PayNowView['kind'] | null;
  selectedCode?: string | null;
  hasOfficePayment?: boolean;
}): boolean {
  if (input.hasOfficePayment) return false;
  if (input.viewKind !== 'selector') return false;
  return input.selectedCode === 'snippe';
}
