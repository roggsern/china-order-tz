import {
  buildHostedCheckoutHtml,
  buildNmbCheckoutScriptUrl,
  customerMessageForHcError,
  hostedCheckoutHtmlAwaitsScriptLoad,
  isNmbAppPaymentReturnUrl,
  MPGS_ERROR_CALLBACK_DEFAULT_MESSAGE,
  normalizeHcErrorStage,
  NMB_HC_DIAGNOSTIC_STAGES,
  resolveNmbWebsiteCheckoutMessageAction,
  safeUrlHost,
  sanitizeHcDiagnosticDetail,
  stageFromMpgsErrorCallback,
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

  it('emits bootstrap_started and awaits script.onload before configure/show', () => {
    expect(hostedCheckoutHtmlAwaitsScriptLoad(html)).toBe(true);
    expect(html).toContain("setMilestone('bootstrap_started')");
    expect(html).toContain("setMilestone('script_loaded')");
    const bootstrapIdx = html.indexOf("setMilestone('bootstrap_started')");
    const onloadIdx = html.indexOf('script.onload');
    const configureIdx = html.indexOf('Checkout.configure');
    expect(bootstrapIdx).toBeGreaterThan(-1);
    expect(bootstrapIdx).toBeLessThan(onloadIdx);
    expect(configureIdx).toBeGreaterThan(onloadIdx);
  });

  it('maps MPGS data-error callback to milestone-based stages (not hardcoded unknown)', () => {
    expect(html).toContain('mpgs_data_error_callback');
    expect(html).toContain("stage = 'mpgs_error'");
    expect(html).toContain("stage = 'configure_failed'");
    expect(html).toContain("stage = 'show_payment_failed'");
    const errorCallbackBlock = html.slice(
      html.indexOf('window.errorCallback'),
      html.indexOf('window.timeoutCallback'),
    );
    expect(errorCallbackBlock).toContain("source: 'mpgs_data_error_callback'");
    expect(errorCallbackBlock).not.toContain("stage: 'unknown'");
  });
});

describe('safeUrlHost', () => {
  it('returns hostname only and strips query', () => {
    expect(
      safeUrlHost(
        'https://test-nmbbank.mtf.gateway.mastercard.com/checkout/pay?resultIndicator=secret',
      ),
    ).toBe('test-nmbbank.mtf.gateway.mastercard.com');
  });
});

describe('stageFromMpgsErrorCallback — proven Build-3 unknown path', () => {
  it('classifies by last milestone', () => {
    expect(stageFromMpgsErrorCallback('script_loading')).toBe('script_load');
    expect(stageFromMpgsErrorCallback('script_loaded')).toBe('configure_failed');
    expect(stageFromMpgsErrorCallback('configure_started')).toBe('configure_failed');
    expect(stageFromMpgsErrorCallback('configure_success')).toBe(
      'show_payment_failed',
    );
    expect(stageFromMpgsErrorCallback('show_started')).toBe('show_payment_failed');
    expect(stageFromMpgsErrorCallback(null)).toBe('mpgs_error');
  });
});

describe('isNmbAppPaymentReturnUrl', () => {
  it('accepts chinaordertz://payment-return only', () => {
    expect(
      isNmbAppPaymentReturnUrl(
        'chinaordertz://payment-return?resultIndicator=abc',
        'chinaordertz',
      ),
    ).toBe(true);
    expect(
      isNmbAppPaymentReturnUrl(
        'https://test-nmbbank.mtf.gateway.mastercard.com/?resultIndicator=x',
        'chinaordertz',
      ),
    ).toBe(false);
  });
});

describe('normalizeHcErrorStage + customer messages', () => {
  it('covers all diagnostic stages', () => {
    for (const stage of NMB_HC_DIAGNOSTIC_STAGES) {
      expect(normalizeHcErrorStage(stage)).toBe(stage);
      expect(customerMessageForHcError(stage)).not.toMatch(/SESSION|secret|token/i);
    }
  });
});

describe('resolveNmbWebsiteCheckoutMessageAction', () => {
  const buildReturnUrl = (ri: string | null) =>
    `chinaordertz://payment-return?resultIndicator=${encodeURIComponent(ri ?? '')}`;

  it('reclassifies legacy unknown + default MPGS message using milestone', () => {
    const action = resolveNmbWebsiteCheckoutMessageAction(
      {
        type: 'error',
        stage: 'unknown',
        message: MPGS_ERROR_CALLBACK_DEFAULT_MESSAGE,
        lastMilestone: 'show_started',
        source: 'mpgs_data_error_callback',
      },
      buildReturnUrl,
    );
    expect(action.kind).toBe('show_error');
    if (action.kind === 'show_error') {
      expect(action.stage).toBe('show_payment_failed');
      expect(action.diagnosticDetail).toContain('Payment could not be opened.');
      expect(action.diagnosticDetail).toContain('milestone=show_started');
      expect(action.diagnosticDetail).toContain('source=mpgs_data_error_callback');
    }
  });

  it.each([
    ['script_load', 'script_onerror'],
    ['checkout_missing', 'missing'],
    ['configure_failed', 'configure threw'],
    ['show_payment_failed', 'show threw'],
    ['mpgs_error', MPGS_ERROR_CALLBACK_DEFAULT_MESSAGE],
    ['mpgs_timeout', 'timed out'],
    ['webview_error', 'WebView onError'],
    ['webview_http_error', 'HTTP 404'],
    ['navigation_failed', 'nav'],
    ['message_parse_failed', 'Malformed'],
    ['html_bootstrap_not_started', 'no bootstrap'],
    ['unknown', 'Unable to start'],
  ] as const)('handles stage %s without completing store', (stage, msg) => {
    const action = resolveNmbWebsiteCheckoutMessageAction(
      { type: 'error', stage, message: msg },
      buildReturnUrl,
    );
    expect(action.kind).toBe('show_error');
    if (action.kind === 'show_error') {
      expect(action.stage).toBe(stage);
    }
  });

  it('tracks diagnostic milestones without closing', () => {
    const action = resolveNmbWebsiteCheckoutMessageAction(
      { type: 'diagnostic', stage: 'bootstrap_started' },
      buildReturnUrl,
    );
    expect(action).toEqual({ kind: 'ignore', milestone: 'bootstrap_started' });
  });

  it('redacts session ids from diagnostic detail', () => {
    const action = resolveNmbWebsiteCheckoutMessageAction(
      {
        type: 'error',
        stage: 'mpgs_error',
        message: 'bad SESSION000999ABCDEF',
      },
      buildReturnUrl,
    );
    expect(action.kind).toBe('show_error');
    if (action.kind === 'show_error') {
      expect(action.diagnosticDetail).not.toMatch(/SESSION000999/i);
      expect(action.diagnosticDetail).toContain('[redacted]');
    }
  });

  it('completes only on complete/cancel', () => {
    expect(
      resolveNmbWebsiteCheckoutMessageAction(
        { type: 'complete', resultIndicator: 'ri-1' },
        buildReturnUrl,
      ).kind,
    ).toBe('complete');
    expect(
      resolveNmbWebsiteCheckoutMessageAction({ type: 'cancel' }, buildReturnUrl)
        .kind,
    ).toBe('complete');
  });
});

describe('sanitizeHcDiagnosticDetail', () => {
  it('redacts session-like tokens', () => {
    expect(sanitizeHcDiagnosticDetail('SESSION000123456789abcdef')).toContain(
      '[redacted]',
    );
  });
});

describe('nmbWebsiteCheckoutStore + error path', () => {
  beforeEach(() => {
    useNmbWebsiteCheckoutStore.setState({ request: null, resolve: null });
  });

  it('does not complete store on show_error', async () => {
    const pending = useNmbWebsiteCheckoutStore.getState().open({
      sessionId: 'SESSION_X',
      gatewayBaseUrl: 'https://test-nmbbank.mtf.gateway.mastercard.com',
    });
    const action = resolveNmbWebsiteCheckoutMessageAction(
      {
        type: 'error',
        stage: 'unknown',
        message: MPGS_ERROR_CALLBACK_DEFAULT_MESSAGE,
        lastMilestone: 'configure_success',
        source: 'mpgs_data_error_callback',
      },
      () => 'chinaordertz://payment-return',
    );
    expect(action.kind).toBe('show_error');
    expect(useNmbWebsiteCheckoutStore.getState().request).not.toBeNull();
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
