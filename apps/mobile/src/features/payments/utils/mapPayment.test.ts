import {
  buildReconcileNmbPayload,
  buildStartPaymentPayload,
  canOpenCheckoutUrl,
  extractNmbReturnParams,
  parsePaymentReturnUrl,
  paymentReturnUrlEmbedsAuthToken,
  isNmbWebsiteHostedCheckout,
  isSuccessfulPaymentStatus,
  isTerminalPaymentStatus,
  mapPaymentMethods,
  mapPaymentOrder,
  mapPreparedPayment,
  isPreparedPaymentPaid,
  mapPaymentTransaction,
  paymentStatusLabel,
} from './mapPayment';

describe('mapPaymentMethods', () => {
  it('maps payment method availability without secrets', () => {
    const methods = mapPaymentMethods({
      default_provider: 'nmb',
      enabled_methods: ['nmb', 'cash'],
      methods: [
        { code: 'nmb', enabled: true, available: true, selectable: true },
        { code: 'cash', enabled: true, available: false, selectable: false },
      ],
    });

    expect(methods.defaultProvider).toBe('nmb');
    expect(methods.enabledMethods).toEqual(['nmb', 'cash']);
    expect(methods.methods[0]).toMatchObject({
      code: 'nmb',
      selectable: true,
    });
  });

  it('trusts backend selectable flags for nmb, snippe, and cash', () => {
    const methods = mapPaymentMethods({
      default_provider: 'nmb',
      enabled_methods: ['nmb', 'snippe', 'cash'],
      methods: [
        { code: 'nmb', enabled: true, available: true, selectable: true },
        { code: 'snippe', enabled: true, available: true, selectable: true },
        { code: 'cash', enabled: true, available: true, selectable: true },
      ],
    });

    expect(methods.methods.find((method) => method.code === 'nmb')?.selectable).toBe(true);
    expect(methods.methods.find((method) => method.code === 'snippe')?.selectable).toBe(true);
    expect(methods.methods.find((method) => method.code === 'cash')?.selectable).toBe(true);
  });

  it('does not override backend when cash is not selectable', () => {
    const methods = mapPaymentMethods({
      default_provider: 'nmb',
      enabled_methods: ['nmb', 'cash'],
      methods: [
        { code: 'nmb', enabled: true, available: true, selectable: true },
        { code: 'cash', enabled: true, available: true, selectable: false },
      ],
    });

    expect(methods.methods.find((method) => method.code === 'cash')?.selectable).toBe(false);
    expect(methods.methods.find((method) => method.code === 'nmb')?.selectable).toBe(true);
  });

  it('maps unknown future method codes without crashing', () => {
    const methods = mapPaymentMethods({
      default_provider: 'future_pay',
      enabled_methods: ['future_pay'],
      methods: [
        { code: 'future_pay', enabled: true, available: true, selectable: true },
      ],
    });

    expect(methods.methods[0]).toMatchObject({
      code: 'future_pay',
      enabled: true,
      available: true,
      selectable: true,
    });
  });
});

describe('buildStartPaymentPayload', () => {
  it('omits provider when unset and includes nmb when provided', () => {
    expect(buildStartPaymentPayload()).toEqual({});
    expect(buildStartPaymentPayload('nmb')).toEqual({ provider: 'nmb' });
  });

  it('includes snippe phone_number without inventing paid state', () => {
    expect(
      buildStartPaymentPayload({
        provider: 'snippe',
        phoneNumber: '0712345678',
      }),
    ).toEqual({ provider: 'snippe', phone_number: '0712345678' });
  });
});

describe('mapPaymentTransaction / checkout_url handling', () => {
  it('maps transaction fields and checkout_url', () => {
    const txn = mapPaymentTransaction({
      id: 'txn-1',
      order_id: 'ord-1',
      provider: 'nmb',
      merchant_reference: 'COTZ-PAY-1',
      currency: 'TZS',
      amount: '25000.00',
      status: 'processing',
      checkout_url:
        'https://test-nmbbank.mtf.gateway.mastercard.com/checkout/pay/1',
      success_indicator: 'si-1',
      order: {
        id: 'ord-1',
        order_number: 'ORD-1',
        status: 'pending_payment',
        grand_total: '25000.00',
        currency: 'TZS',
      },
    });

    expect(txn).toMatchObject({
      id: 'txn-1',
      orderId: 'ord-1',
      checkoutUrl:
        'https://test-nmbbank.mtf.gateway.mastercard.com/checkout/pay/1',
      successIndicator: 'si-1',
      status: 'processing',
    });
    expect(canOpenCheckoutUrl(txn.checkoutUrl)).toBe(true);
    expect(canOpenCheckoutUrl(null)).toBe(false);
    expect(canOpenCheckoutUrl('not-a-url')).toBe(false);
    expect(
      canOpenCheckoutUrl('http://test-nmbbank.mtf.gateway.mastercard.com/pay/1'),
    ).toBe(false);
    expect(canOpenCheckoutUrl('https://evil-domain.com/pay')).toBe(false);
  });

  it('treats NMB session-without-url as Website Hosted Checkout eligible', () => {
    const txn = mapPaymentTransaction({
      id: 'txn-2',
      order_id: 'ord-2',
      provider: 'nmb',
      merchant_reference: 'COTZ-PAY-2',
      currency: 'TZS',
      amount: '25000.00',
      status: 'processing',
      checkout_url: null,
      provider_reference: 'SESSION000ABC',
      success_indicator: 'si-2',
    });

    expect(txn.checkoutUrl).toBeNull();
    expect(txn.providerReference).toBe('SESSION000ABC');
    expect(isNmbWebsiteHostedCheckout(txn)).toBe(true);
    expect(canOpenCheckoutUrl(txn.checkoutUrl)).toBe(false);
  });
});

describe('browser return + reconcile payload', () => {
  it('extracts NMB return params without treating them as paid', () => {
    const params = extractNmbReturnParams(
      'chinaordertz://payment-return?resultIndicator=ri-9&order_id=ord-1&merchant_reference=COTZ-PAY-1',
    );
    expect(params.resultIndicator).toBe('ri-9');
    expect(params.orderId).toBe('ord-1');
    expect(params.merchantReference).toBe('COTZ-PAY-1');
    expect(isSuccessfulPaymentStatus(undefined)).toBe(false);
    expect(
      paymentReturnUrlEmbedsAuthToken(
        'chinaordertz://payment-return?resultIndicator=ri-9&order_id=ord-1',
      ),
    ).toBe(false);
  });

  it('parses a generic payment-return URL without NMB-only assumptions', () => {
    expect(
      parsePaymentReturnUrl(
        'chinaordertz://payment-return?order_id=ord-snippe&paymentTransactionId=txn-snippe-1',
      ),
    ).toEqual({
      resultIndicator: null,
      orderId: 'ord-snippe',
      merchantReference: null,
      paymentTransactionId: 'txn-snippe-1',
      embedsAuthToken: false,
    });
  });

  it('flags an auth token incorrectly embedded in a deep link', () => {
    expect(
      paymentReturnUrlEmbedsAuthToken(
        'chinaordertz://payment-return?token=secret-sanctum&order_id=ord-1',
      ),
    ).toBe(true);
  });

  it('builds reconcile payload for server proof', () => {
    expect(
      buildReconcileNmbPayload({
        paymentTransactionId: 'txn-1',
        merchantReference: 'COTZ-PAY-1',
        successIndicator: 'si-1',
        resultIndicator: 'ri-9',
        orderId: 'ord-1',
      }),
    ).toEqual({
      payment_transaction_id: 'txn-1',
      merchant_reference: 'COTZ-PAY-1',
      success_indicator: 'si-1',
      result_indicator: 'ri-9',
      order_id: 'ord-1',
    });
  });
});

describe('status helpers', () => {
  it('maps server statuses to friendly labels and terminal checks', () => {
    expect(paymentStatusLabel('successful')).toBe('Paid');
    expect(paymentStatusLabel('failed')).toBe('Failed');
    expect(isTerminalPaymentStatus('successful')).toBe(true);
    expect(isTerminalPaymentStatus('processing')).toBe(false);
    expect(isSuccessfulPaymentStatus('successful')).toBe(true);
  });
});

describe('mapPreparedPayment', () => {
  it('maps Pay at Office preparation and does not treat initiated as paid', () => {
    const prepared = mapPreparedPayment({
      id: 'pay-office-1',
      order_id: 'ord-1',
      payment_method: 'cash',
      status: 'initiated',
      ready_for_payment: true,
      currency: 'TZS',
      amount: '1000',
    });
    expect(prepared.paymentMethod).toBe('cash');
    expect(isPreparedPaymentPaid(prepared.status)).toBe(false);
    expect(isSuccessfulPaymentStatus('processing')).toBe(false);
  });
});

describe('mapPaymentOrder', () => {
  it('maps from-checkout order identity for payment start', () => {
    expect(
      mapPaymentOrder({
        id: 'ord-1',
        order_number: 'ORD-100',
        status: 'pending_payment',
        currency: 'TZS',
        grand_total: '1000',
        checkout_session_id: 'sess-1',
      }),
    ).toMatchObject({
      id: 'ord-1',
      orderNumber: 'ORD-100',
      checkoutSessionId: 'sess-1',
      grandTotal: '1000',
    });
  });
});
