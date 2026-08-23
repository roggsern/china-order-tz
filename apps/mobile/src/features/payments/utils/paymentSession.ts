import type { ActivePaymentTransactionRef } from '../models/types';
import type { PayNowView } from './payNowRecovery';
import {
  isMobileSupportedPaymentMethod,
  type MobileSupportedPaymentMethod,
} from './paymentAvailability';
import { resolveRefreshedTransactionView } from './payNowRecovery';

export type PaymentStartDecision =
  | { decision: 'start'; flow: MobileSupportedPaymentMethod }
  | { decision: 'recover'; transaction: ActivePaymentTransactionRef }
  | { decision: 'paid' }
  | { decision: 'not_payable'; reason: 'paid' | 'cancelled' | 'other' }
  | { decision: 'unsupported'; code: string }
  | { decision: 'blocked'; reason: 'recovery' | 'paid' | 'not_payable' };

/**
 * Decide whether a customer action may start a new provider.
 * Active pending/processing attempts must recover, never switch providers.
 */
export function resolvePaymentStartDecision(input: {
  view: PayNowView;
  selectedCode?: string | null;
}): PaymentStartDecision {
  if (input.view.kind === 'paid') {
    return { decision: 'paid' };
  }

  if (input.view.kind === 'not_payable') {
    return { decision: 'not_payable', reason: input.view.reason };
  }

  if (input.view.kind === 'recovery') {
    return { decision: 'recover', transaction: input.view.transaction };
  }

  const code = input.selectedCode?.trim() ?? '';
  if (!code) {
    return { decision: 'blocked', reason: 'not_payable' };
  }

  if (!isMobileSupportedPaymentMethod(code)) {
    return { decision: 'unsupported', code };
  }

  return { decision: 'start', flow: code };
}

export function canStartNewPayment(view: PayNowView): boolean {
  return view.kind === 'selector';
}

export function applyRefreshedTransaction(
  transaction: ActivePaymentTransactionRef,
): PayNowView {
  const view = resolveRefreshedTransactionView(transaction.status);
  if (view === 'paid') {
    return { kind: 'paid' };
  }
  if (view === 'recovery') {
    return { kind: 'recovery', transaction };
  }
  return { kind: 'selector' };
}

export function paymentProviderLabel(provider: string | null | undefined): string {
  if (provider === 'snippe') return 'Mobile Money';
  if (provider === 'nmb') return 'NMB';
  if (provider === 'cash') return 'Pay at Office';
  return provider?.trim() || 'payment';
}

export function unsupportedPaymentMethodMessage(): string {
  return 'This payment method is not available in the app yet. Please choose another option.';
}
