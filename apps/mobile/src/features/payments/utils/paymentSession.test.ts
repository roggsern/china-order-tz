import { ApiError } from '@/src/core/errors';
import { isOrderPayableFromServer } from '@/src/features/orders/utils/isOrderPayable';
import { mapPreparedPayment, isPreparedPaymentPaid } from './mapPayment';
import {
  applyRefreshedTransaction,
  canStartNewPayment,
  paymentProviderLabel,
  resolvePaymentStartDecision,
  unsupportedPaymentMethodMessage,
} from './paymentSession';
import {
  isPaymentInProgressError,
  recoveryFromStartError,
  resolvePayNowView,
} from './payNowRecovery';

const processingSnippe = {
  id: 'txn-snippe-1',
  status: 'processing',
  provider: 'snippe',
};

const processingNmb = {
  id: 'txn-nmb-1',
  status: 'processing',
  provider: 'nmb',
};

describe('payment start decisions', () => {
  it('starts NMB, Snippe, or cash only from the selector', () => {
    const selector = resolvePayNowView({
      canPay: true,
      orderStatus: 'pending_payment',
    });
    expect(resolvePaymentStartDecision({ view: selector, selectedCode: 'nmb' })).toEqual({
      decision: 'start',
      flow: 'nmb',
    });
    expect(resolvePaymentStartDecision({ view: selector, selectedCode: 'snippe' })).toEqual({
      decision: 'start',
      flow: 'snippe',
    });
    expect(resolvePaymentStartDecision({ view: selector, selectedCode: 'cash' })).toEqual({
      decision: 'start',
      flow: 'cash',
    });
  });

  it('does not start an unsupported selectable provider', () => {
    const selector = resolvePayNowView({
      canPay: true,
      orderStatus: 'pending_payment',
    });
    expect(
      resolvePaymentStartDecision({ view: selector, selectedCode: 'future_pay' }),
    ).toEqual({ decision: 'unsupported', code: 'future_pay' });
    expect(unsupportedPaymentMethodMessage()).toMatch(/isn't available yet/i);
  });

  it('recovers processing Snippe instead of starting NMB', () => {
    const view = resolvePayNowView({
      canPay: true,
      orderStatus: 'pending_payment',
      activeTransaction: processingSnippe,
    });
    expect(canStartNewPayment(view)).toBe(false);
    expect(
      resolvePaymentStartDecision({ view, selectedCode: 'nmb' }),
    ).toEqual({ decision: 'recover', transaction: processingSnippe });
  });

  it('recovers processing NMB instead of starting Snippe', () => {
    const view = resolvePayNowView({
      canPay: true,
      orderStatus: 'pending_payment',
      activeTransaction: processingNmb,
    });
    expect(
      resolvePaymentStartDecision({ view, selectedCode: 'snippe' }),
    ).toEqual({ decision: 'recover', transaction: processingNmb });
  });

  it('does not switch a processing provider to Pay at Office', () => {
    const view = resolvePayNowView({
      canPay: true,
      orderStatus: 'pending_payment',
      activeTransaction: processingSnippe,
    });
    expect(
      resolvePaymentStartDecision({ view, selectedCode: 'cash' }),
    ).toEqual({ decision: 'recover', transaction: processingSnippe });
  });

  it('maps payment_in_progress onto recovery without a new start', () => {
    const error = new ApiError({
      message: 'An active payment is already in progress for this order.',
      status: 422,
      code: 'payment_in_progress',
      raw: {
        code: 'payment_in_progress',
        payment_transaction_id: 'txn-active-1',
        payment_transaction_status: 'processing',
        provider: 'snippe',
      },
    });

    expect(isPaymentInProgressError(error)).toBe(true);
    expect(recoveryFromStartError(error)).toEqual({
      id: 'txn-active-1',
      status: 'processing',
      provider: 'snippe',
    });
    expect(resolvePaymentStartDecision({
      view: { kind: 'recovery', transaction: recoveryFromStartError(error)! },
      selectedCode: 'nmb',
    }).decision).toBe('recover');
  });
});

describe('Pay Now classification', () => {
  it('shows selector for payable orders with no active transaction', () => {
    expect(
      resolvePayNowView({
        canPay: true,
        orderStatus: 'pending_payment',
        activeTransaction: null,
      }),
    ).toEqual({ kind: 'selector' });
  });

  it('recovers pending and processing backend transactions after local state is lost', () => {
    expect(
      resolvePayNowView({
        canPay: true,
        orderStatus: 'pending_payment',
        activeTransaction: { id: 'txn-1', status: 'pending', provider: 'nmb' },
      }).kind,
    ).toBe('recovery');
    expect(
      resolvePayNowView({
        canPay: true,
        orderStatus: 'pending_payment',
        activeTransaction: processingSnippe,
      }).kind,
    ).toBe('recovery');
  });

  it('does not start again after a successful transaction', () => {
    expect(applyRefreshedTransaction({
      id: 'txn-ok',
      status: 'successful',
      provider: 'snippe',
    })).toEqual({ kind: 'paid' });
    expect(
      resolvePaymentStartDecision({
        view: { kind: 'paid' },
        selectedCode: 'nmb',
      }),
    ).toEqual({ decision: 'paid' });
  });

  it('returns failed, expired, or voided attempts to the selector', () => {
    expect(applyRefreshedTransaction({
      id: 'txn-fail',
      status: 'failed',
      provider: 'nmb',
    })).toEqual({ kind: 'selector' });
    expect(applyRefreshedTransaction({
      id: 'txn-exp',
      status: 'expired',
      provider: 'snippe',
    })).toEqual({ kind: 'selector' });
    expect(applyRefreshedTransaction({
      id: 'txn-void',
      status: 'voided',
      provider: 'snippe',
    })).toEqual({ kind: 'selector' });
  });

  it('keeps Pay Now off cancelled and refunded orders even with a stale processing txn', () => {
    expect(
      isOrderPayableFromServer({
        status: 'cancelled',
        canPay: true,
      }),
    ).toBe(false);
    expect(
      resolvePayNowView({
        canPay: true,
        orderStatus: 'cancelled',
        activeTransaction: processingNmb,
      }),
    ).toEqual({ kind: 'not_payable', reason: 'cancelled' });
    expect(
      resolvePayNowView({
        canPay: true,
        orderStatus: 'refunded',
        activeTransaction: processingSnippe,
      }),
    ).toEqual({ kind: 'not_payable', reason: 'cancelled' });
  });

  it('still offers Pay Now for an old unpaid backend-payable order', () => {
    expect(
      isOrderPayableFromServer({
        status: 'pending_payment',
        canPay: true,
        paymentStatus: 'pending',
      }),
    ).toBe(true);
  });
});

describe('Pay at Office preparation', () => {
  it('maps cash preparation without marking the order paid', () => {
    const prepared = mapPreparedPayment({
      id: 'pay-1',
      reference: 'CASH-1',
      order_id: 'ord-1',
      order_number: 'COTZ-1',
      amount: '25000.00',
      currency: 'TZS',
      payment_method: 'cash',
      status: 'initiated',
      ready_for_payment: true,
    });

    expect(prepared.paymentMethod).toBe('cash');
    expect(prepared.status).toBe('initiated');
    expect(prepared.readyForPayment).toBe(true);
    expect(isPreparedPaymentPaid(prepared.status)).toBe(false);
    expect(paymentProviderLabel('cash')).toBe('Pay at Office');
    expect(paymentProviderLabel('cash')).not.toMatch(/cod|delivery/i);
  });
});
