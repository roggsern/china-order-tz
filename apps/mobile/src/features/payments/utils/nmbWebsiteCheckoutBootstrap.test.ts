import {
  buildHostedCheckoutHtml,
  buildNmbCheckoutScriptUrl,
  hostedCheckoutHtmlAwaitsScriptLoad,
  isNmbAppPaymentReturnUrl,
  resolveNmbWebsiteCheckoutMessageAction,
  NMB_WEBSITE_HC_CUSTOMER_ERROR_RETRY,
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
    expect(html).toContain('script.onload');
    expect(html).toContain('Checkout.configure');
    expect(html).toContain('showPaymentPage');
    const onloadIdx = html.indexOf('script.onload');
    const configureIdx = html.indexOf('Checkout.configure');
    const showIdx = html.indexOf('showPaymentPage');
    expect(onloadIdx).toBeGreaterThan(-1);
    expect(configureIdx).toBeGreaterThan(onloadIdx);
    expect(showIdx).toBeGreaterThan(configureIdx);
  });

  it('posts structured script_load errors when Checkout is missing after load', () => {
    expect(html).toContain("stage: 'script_load'");
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
    expect(
      isNmbAppPaymentReturnUrl(
        'https://ap.gateway.mastercard.com/?resultIndicator=x',
        'chinaordertz',
      ),
    ).toBe(false);
  });

  it('rejects unrelated custom schemes', () => {
    expect(
      isNmbAppPaymentReturnUrl('https://evil.example/payment-return', 'chinaordertz'),
    ).toBe(false);
  });
});

describe('resolveNmbWebsiteCheckoutMessageAction', () => {
  const buildReturnUrl = (ri: string | null) =>
    `chinaordertz://payment-return?resultIndicator=${encodeURIComponent(ri ?? '')}`;

  it('does not complete the store on WebView bootstrap errors', () => {
    const action = resolveNmbWebsiteCheckoutMessageAction(
      { type: 'error', stage: 'script_load', message: 'Failed to load SDK' },
      buildReturnUrl,
    );
    expect(action).toEqual({
      kind: 'show_error',
      stage: 'script_load',
      customerMessage: NMB_WEBSITE_HC_CUSTOMER_ERROR_RETRY,
    });
  });

  it('keeps configure/show/runtime errors as show_error without complete', () => {
    for (const stage of ['configure', 'show', 'runtime'] as const) {
      const action = resolveNmbWebsiteCheckoutMessageAction(
        { type: 'error', stage, message: 'internal' },
        buildReturnUrl,
      );
      expect(action.kind).toBe('show_error');
      if (action.kind === 'show_error') {
        expect(action.customerMessage).not.toMatch(/internal|SESSION|secret/i);
      }
    }
  });

  it('completes only on confirmed complete or cancel', () => {
    const complete = resolveNmbWebsiteCheckoutMessageAction(
      { type: 'complete', resultIndicator: 'ri-1' },
      buildReturnUrl,
    );
    expect(complete.kind).toBe('complete');
    if (complete.kind === 'complete') {
      expect(complete.result.type).toBe('success');
      expect(complete.result.returnParams.resultIndicator).toBe('ri-1');
    }

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

    expect(useNmbWebsiteCheckoutStore.getState().request).not.toBeNull();

    const action = resolveNmbWebsiteCheckoutMessageAction(
      { type: 'error', stage: 'script_load' },
      () => 'chinaordertz://payment-return',
    );
    expect(action.kind).toBe('show_error');
    // Modal must NOT call complete() for show_error — request stays for retry UI.
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
