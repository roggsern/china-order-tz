import {
  buildHostedCheckoutHtml,
  buildNmbCheckoutScriptUrl,
  customerMessageForHcError,
  hostedCheckoutHtmlAwaitsScriptLoad,
  isNmbAppPaymentReturnUrl,
  normalizeHcErrorStage,
  NMB_HC_DIAGNOSTIC_STAGES,
  resolveNmbWebsiteCheckoutMessageAction,
  sanitizeHcDiagnosticDetail,
} from './nmbWebsiteCheckoutBootstrap';
import { useNmbWebsiteCheckoutStore } from '../state/nmbWebsiteCheckoutStore';

describe('buildHostedCheckoutHtml / Checkout.js bootstrap', () => {
  const html = buildHostedCheckoutHtml(
    'SESSION_TEST_ID',
    'https://test-nmbbank.mtf.gateway.mastercard.com',
  );

  it('builds the Mastercard Checkout.js URL from the gateway base', () => {
    expect(
      buildNmbCheckoutScriptUrl('https://test-nmbbank.mtf.gateway.mastercard.com/'),
    ).toBe(
      'https://test-nmbbank.mtf.gateway.mastercard.com/static/checkout/checkout.min.js',
    );
  });

  it('awaits script.onload before configure and showPaymentPage', () => {
    expect(hostedCheckoutHtmlAwaitsScriptLoad(html)).toBe(true);
    const onloadIdx = html.indexOf('script.onload');
    const configureIdx = html.indexOf('Checkout.configure');
    const showIdx = html.indexOf('showPaymentPage');
    expect(onloadIdx).toBeGreaterThan(-1);
    expect(configureIdx).toBeGreaterThan(onloadIdx);
    expect(showIdx).toBeGreaterThan(configureIdx);
  });

  it('emits diagnostic stages for script_load, checkout_missing, configure, show', () => {
    expect(html).toContain("stage: 'script_load'");
    expect(html).toContain("stage: 'checkout_missing'");
    expect(html).toContain("stage: 'configure_failed'");
    expect(html).toContain("stage: 'show_payment_failed'");
    expect(html).toContain('script.onerror');
  });
});

describe('isNmbAppPaymentReturnUrl', () => {
  it('accepts chinaordertz://payment-return deep links', () => {
    expect(
      isNmbAppPaymentReturnUrl(
        'chinaordertz://payment-return?resultIndicator=abc',
        'chinaordertz',
      ),
    ).toBe(true);
  });

  it('rejects gateway URLs that only contain resultIndicator', () => {
    expect(
      isNmbAppPaymentReturnUrl(
        'https://test-nmbbank.mtf.gateway.mastercard.com/checkout/pay?resultIndicator=x',
        'chinaordertz',
      ),
    ).toBe(false);
  });
});

describe('sanitizeHcDiagnosticDetail', () => {
  it('redacts session-like tokens and long opaque ids', () => {
    expect(
      sanitizeHcDiagnosticDetail('Failed SESSION000123456789abcdef configure'),
    ).toContain('[redacted]');
    expect(
      sanitizeHcDiagnosticDetail('id=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),
    ).toContain('[redacted]');
  });
});

describe('normalizeHcErrorStage + customer messages for every diagnostic stage', () => {
  it('covers all required diagnostic stages', () => {
    expect([...NMB_HC_DIAGNOSTIC_STAGES]).toEqual([
      'script_load',
      'checkout_missing',
      'configure_failed',
      'show_payment_failed',
      'navigation_failed',
      'unknown',
    ]);
  });

  it.each(NMB_HC_DIAGNOSTIC_STAGES)(
    'normalizes and maps customer message for %s',
    (stage) => {
      expect(normalizeHcErrorStage(stage)).toBe(stage);
      const message = customerMessageForHcError(stage);
      expect(message.length).toBeGreaterThan(10);
      expect(message).not.toMatch(/SESSION|secret|password|token/i);
    },
  );

  it('maps legacy stage aliases', () => {
    expect(normalizeHcErrorStage('configure')).toBe('configure_failed');
    expect(normalizeHcErrorStage('show')).toBe('show_payment_failed');
    expect(normalizeHcErrorStage('runtime')).toBe('unknown');
    expect(normalizeHcErrorStage('nope')).toBe('unknown');
  });
});

describe('resolveNmbWebsiteCheckoutMessageAction diagnostic stages', () => {
  const buildReturnUrl = (ri: string | null) =>
    `chinaordertz://payment-return?resultIndicator=${encodeURIComponent(ri ?? '')}`;

  it.each([
    ['script_load', 'Failed to load Hosted Checkout script.'],
    ['checkout_missing', 'Checkout object missing after script load.'],
    ['configure_failed', 'Checkout.configure failed.'],
    ['show_payment_failed', 'Checkout.showPaymentPage failed.'],
    ['navigation_failed', 'WebView onError'],
    ['unknown', 'Payment could not be opened.'],
  ] as const)(
    'returns show_error with stage %s and does not complete',
    (stage, rawMessage) => {
      const action = resolveNmbWebsiteCheckoutMessageAction(
        { type: 'error', stage, message: rawMessage },
        buildReturnUrl,
      );
      expect(action.kind).toBe('show_error');
      if (action.kind === 'show_error') {
        expect(action.stage).toBe(stage);
        expect(action.customerMessage).toBe(customerMessageForHcError(stage));
        expect(action.diagnosticDetail).toBe(sanitizeHcDiagnosticDetail(rawMessage));
        expect(action.customerMessage).not.toMatch(/SESSION|secret/i);
      }
    },
  );

  it('redacts session ids from diagnostic detail in show_error', () => {
    const action = resolveNmbWebsiteCheckoutMessageAction(
      {
        type: 'error',
        stage: 'configure_failed',
        message: 'bad SESSION000999ABCDEF payload',
      },
      buildReturnUrl,
    );
    expect(action.kind).toBe('show_error');
    if (action.kind === 'show_error') {
      expect(action.diagnosticDetail).not.toMatch(/SESSION000999/i);
      expect(action.diagnosticDetail).toContain('[redacted]');
    }
  });

  it('completes only on confirmed complete or cancel', () => {
    const complete = resolveNmbWebsiteCheckoutMessageAction(
      { type: 'complete', resultIndicator: 'ri-1' },
      buildReturnUrl,
    );
    expect(complete.kind).toBe('complete');

    const cancel = resolveNmbWebsiteCheckoutMessageAction(
      { type: 'cancel' },
      buildReturnUrl,
    );
    expect(cancel.kind).toBe('complete');
    if (cancel.kind === 'complete') {
      expect(cancel.result.type).toBe('cancel');
    }
  });

  it('ignores stage diagnostics without closing', () => {
    expect(
      resolveNmbWebsiteCheckoutMessageAction(
        { type: 'stage', stage: 'script_loaded' },
        buildReturnUrl,
      ),
    ).toEqual({ kind: 'ignore' });
  });
});

describe('nmbWebsiteCheckoutStore + error path', () => {
  beforeEach(() => {
    useNmbWebsiteCheckoutStore.setState({ request: null, resolve: null });
  });

  it('leaves the checkout request open when bootstrap errors are handled as show_error', async () => {
    const pending = useNmbWebsiteCheckoutStore.getState().open({
      sessionId: 'SESSION_X',
      gatewayBaseUrl: 'https://test-nmbbank.mtf.gateway.mastercard.com',
    });

    const action = resolveNmbWebsiteCheckoutMessageAction(
      { type: 'error', stage: 'script_load', message: 'Failed to load' },
      () => 'chinaordertz://payment-return',
    );
    expect(action.kind).toBe('show_error');
    expect(useNmbWebsiteCheckoutStore.getState().request?.sessionId).toBe(
      'SESSION_X',
    );

    useNmbWebsiteCheckoutStore.getState().complete({
      type: 'cancel',
      url: null,
      returnParams: {
        resultIndicator: null,
        orderId: null,
        merchantReference: null,
        paymentTransactionId: null,
      },
    });
    await expect(pending).resolves.toMatchObject({ type: 'cancel' });
  });
});
