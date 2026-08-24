import {
  isSnippePhoneEntryVisible,
  resolveSnippePhonePrefill,
} from './snippePhonePrefill';
import {
  formatSnippePhoneForInput,
  normalizeSnippePhone,
  validateSnippePhoneInput,
} from './snippePhone';
import { buildStartPaymentPayload } from './mapPayment';
import {
  canStartNewPayment,
  resolvePaymentStartDecision,
} from './paymentSession';
import { resolvePayNowView } from './payNowRecovery';

const PROFILE_PHONE = '0712345678';
const EDITED_PHONE = '0781000000';

describe('Snippe phone prefill from account', () => {
  it('prefills from a usable profile/account phone', () => {
    expect(
      resolveSnippePhonePrefill({
        profilePhone: PROFILE_PHONE,
        currentValue: '',
        editedInSession: false,
      }),
    ).toBe('0712345678');
  });

  it('leaves the field empty when profile phone is null', () => {
    expect(
      resolveSnippePhonePrefill({
        profilePhone: null,
        currentValue: '',
        editedInSession: false,
      }),
    ).toBe('');
  });

  it('leaves the field empty when profile phone is blank', () => {
    expect(
      resolveSnippePhonePrefill({
        profilePhone: '   ',
        currentValue: '',
        editedInSession: false,
      }),
    ).toBe('');
    expect(
      resolveSnippePhonePrefill({
        profilePhone: '',
        currentValue: '',
        editedInSession: false,
      }),
    ).toBe('');
  });

  it('normalizes a local-format profile phone with existing Wave 1 rules', () => {
    expect(normalizeSnippePhone('0712345678')).toBe('255712345678');
    expect(formatSnippePhoneForInput('0712345678')).toBe('0712345678');
    expect(
      resolveSnippePhonePrefill({
        profilePhone: '0712345678',
        currentValue: '',
        editedInSession: false,
      }),
    ).toBe('0712345678');
  });

  it('handles +255 and 255 profile phones with existing Wave 1 rules', () => {
    expect(normalizeSnippePhone('+255712345678')).toBe('255712345678');
    expect(normalizeSnippePhone('255712345678')).toBe('255712345678');
    expect(formatSnippePhoneForInput('+255712345678')).toBe('0712345678');
    expect(
      resolveSnippePhonePrefill({
        profilePhone: '+255712345678',
        currentValue: '',
        editedInSession: false,
      }),
    ).toBe('0712345678');
    expect(
      resolveSnippePhonePrefill({
        profilePhone: '255712345678',
        currentValue: '',
        editedInSession: false,
      }),
    ).toBe('0712345678');
  });

  it('preserves a customer-edited number and never overwrites it', () => {
    expect(
      resolveSnippePhonePrefill({
        profilePhone: PROFILE_PHONE,
        currentValue: EDITED_PHONE,
        editedInSession: true,
      }),
    ).toBe(EDITED_PHONE);
  });

  it('keeps the edited Snippe number after switching Snippe → NMB → Snippe', () => {
    const afterEdit = resolveSnippePhonePrefill({
      profilePhone: PROFILE_PHONE,
      currentValue: EDITED_PHONE,
      editedInSession: true,
    });

    const afterNmb = resolveSnippePhonePrefill({
      profilePhone: PROFILE_PHONE,
      currentValue: afterEdit,
      editedInSession: true,
    });
    expect(isSnippePhoneEntryVisible({
      viewKind: 'selector',
      selectedCode: 'nmb',
    })).toBe(false);

    expect(
      resolveSnippePhonePrefill({
        profilePhone: PROFILE_PHONE,
        currentValue: afterNmb,
        editedInSession: true,
      }),
    ).toBe(EDITED_PHONE);
    expect(
      isSnippePhoneEntryVisible({
        viewKind: 'selector',
        selectedCode: 'snippe',
      }),
    ).toBe(true);
  });

  it('uses the latest profile phone on a new payment session', () => {
    const previousSessionEdited = resolveSnippePhonePrefill({
      profilePhone: PROFILE_PHONE,
      currentValue: EDITED_PHONE,
      editedInSession: true,
    });
    expect(previousSessionEdited).toBe(EDITED_PHONE);

    expect(
      resolveSnippePhonePrefill({
        profilePhone: '0750000000',
        currentValue: '',
        editedInSession: false,
      }),
    ).toBe('0750000000');
  });

  it('prefills Pay Now when a fresh selector is shown', () => {
    const view = resolvePayNowView({
      canPay: true,
      orderStatus: 'pending_payment',
      paymentStatus: 'pending',
    });
    expect(view.kind).toBe('selector');
    expect(canStartNewPayment(view)).toBe(true);
    expect(
      isSnippePhoneEntryVisible({
        viewKind: view.kind,
        selectedCode: 'snippe',
      }),
    ).toBe(true);
    expect(
      resolveSnippePhonePrefill({
        profilePhone: PROFILE_PHONE,
        currentValue: '',
        editedInSession: false,
      }),
    ).toBe('0712345678');
  });

  it('does not reopen or edit phone during active Snippe recovery', () => {
    const active = {
      id: 'txn-snippe-1',
      status: 'processing' as const,
      provider: 'snippe',
    };
    const view = resolvePayNowView({
      canPay: true,
      orderStatus: 'pending_payment',
      paymentStatus: 'initiated',
      activeTransaction: active,
    });
    expect(view).toEqual({ kind: 'recovery', transaction: active });
    expect(canStartNewPayment(view)).toBe(false);
    expect(
      resolvePaymentStartDecision({ view, selectedCode: 'snippe' }),
    ).toEqual({ decision: 'recover', transaction: active });
    expect(
      isSnippePhoneEntryVisible({
        viewKind: view.kind,
        selectedCode: 'snippe',
      }),
    ).toBe(false);
  });

  it('does not show or use the account phone for NMB', () => {
    const view = resolvePayNowView({
      canPay: true,
      orderStatus: 'pending_payment',
    });
    expect(
      isSnippePhoneEntryVisible({
        viewKind: view.kind,
        selectedCode: 'nmb',
      }),
    ).toBe(false);
    expect(
      resolvePaymentStartDecision({ view, selectedCode: 'nmb' }),
    ).toEqual({ decision: 'start', flow: 'nmb' });
    expect(buildStartPaymentPayload({ provider: 'nmb' })).toEqual({
      provider: 'nmb',
    });
  });

  it('does not show or use the account phone for Pay at Office', () => {
    const view = resolvePayNowView({
      canPay: true,
      orderStatus: 'pending_payment',
    });
    expect(
      isSnippePhoneEntryVisible({
        viewKind: view.kind,
        selectedCode: 'cash',
        hasOfficePayment: true,
      }),
    ).toBe(false);
    expect(
      resolvePaymentStartDecision({ view, selectedCode: 'cash' }),
    ).toEqual({ decision: 'start', flow: 'cash' });
  });

  it('does not auto-start payment from an invalid profile phone', () => {
    const prefill = resolveSnippePhonePrefill({
      profilePhone: '0212345678',
      currentValue: '',
      editedInSession: false,
    });
    expect(prefill).toBe('');
    expect(validateSnippePhoneInput(prefill)).toMatch(
      /enter your mobile money number/i,
    );
    expect(normalizeSnippePhone('0212345678')).toBeNull();
    expect(
      buildStartPaymentPayload({
        provider: 'snippe',
        phoneNumber: prefill,
      }),
    ).toEqual({ provider: 'snippe' });
  });
});
