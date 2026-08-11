import { env } from '@/src/core/config';
import { extractNmbReturnParams } from './mapPayment';
import type { NmbBrowserReturnParams } from '../models/types';
import type { NmbBrowserSessionResult } from './nmbBrowser';

export type NmbWebsiteCheckoutPhase =
  | 'opening'
  | 'loading'
  | 'ready'
  | 'failed';

/** Temporary diagnostic stages for Build verification (shown in UI). */
export type NmbWebsiteCheckoutErrorStage =
  | 'script_load'
  | 'checkout_missing'
  | 'configure_failed'
  | 'show_payment_failed'
  | 'navigation_failed'
  | 'unknown';

export const NMB_HC_DIAGNOSTIC_STAGES: readonly NmbWebsiteCheckoutErrorStage[] = [
  'script_load',
  'checkout_missing',
  'configure_failed',
  'show_payment_failed',
  'navigation_failed',
  'unknown',
] as const;

export type NmbWebsiteCheckoutWebMessage = {
  type?: string;
  stage?: string | null;
  resultIndicator?: string | null;
  message?: string | null;
};

export type NmbWebsiteCheckoutMessageAction =
  | { kind: 'ignore' }
  | {
      kind: 'show_error';
      stage: NmbWebsiteCheckoutErrorStage;
      customerMessage: string;
      diagnosticDetail: string;
    }
  | { kind: 'complete'; result: NmbBrowserSessionResult };

const EMPTY_RETURN: NmbBrowserReturnParams = {
  resultIndicator: null,
  orderId: null,
  merchantReference: null,
  paymentTransactionId: null,
};

export const NMB_WEBSITE_HC_CUSTOMER_ERROR =
  'Unable to open secure payment. Please retry.';

export const NMB_WEBSITE_HC_CUSTOMER_ERROR_RETRY =
  'Secure payment could not start. Please retry.';

/** Dev-only HC stage diagnostics — never logs session ids or secrets. */
export function logNmbHcStage(
  stage: string,
  detail?: Record<string, string | number | boolean | null | undefined>,
): void {
  if (typeof __DEV__ === 'undefined' || !__DEV__) return;
  console.info('[nmb][HC_STAGE]', stage, detail ?? {});
}

export function buildNmbCheckoutScriptUrl(gatewayBaseUrl: string): string {
  return `${gatewayBaseUrl.replace(/\/$/, '')}/static/checkout/checkout.min.js`;
}

/**
 * Strip session ids / long opaque tokens from diagnostic copy before UI display.
 */
export function sanitizeHcDiagnosticDetail(
  message: string | null | undefined,
): string {
  if (typeof message !== 'string' || message.trim() === '') {
    return 'No additional detail.';
  }
  let text = message.trim().slice(0, 180);
  text = text.replace(/SESSION[0-9A-Za-z_-]{6,}/gi, '[redacted]');
  text = text.replace(/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/gi, '[redacted]');
  text = text.replace(/\b[A-Za-z0-9_-]{32,}\b/g, '[redacted]');
  return text;
}

/**
 * Injected HTML: load Checkout.js via onload, then configure + show.
 * Posts diagnostic stages for temporary on-device verification UI.
 */
export function buildHostedCheckoutHtml(
  sessionId: string,
  gatewayBaseUrl: string,
): string {
  const scriptUrl = buildNmbCheckoutScriptUrl(gatewayBaseUrl);
  const safeSession = JSON.stringify(sessionId);
  const safeScript = JSON.stringify(scriptUrl);

  return `<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Secure payment</title>
    <style>
      body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; padding: 24px; color: #222; }
      .msg { margin-top: 12px; color: #555; font-size: 14px; }
    </style>
  </head>
  <body>
    <p id="status">Opening secure payment…</p>
    <p class="msg">Do not close this window while payment loads.</p>
    <script>
      (function () {
        function post(payload) {
          try {
            if (window.ReactNativeWebView) {
              window.ReactNativeWebView.postMessage(JSON.stringify(payload));
            }
          } catch (e) {}
        }

        function setStatus(text) {
          try {
            var el = document.getElementById('status');
            if (el) el.textContent = text;
          } catch (e) {}
        }

        window.completeCallback = function (resultIndicator) {
          post({ type: 'complete', resultIndicator: resultIndicator || null });
        };
        window.cancelCallback = function () {
          post({ type: 'cancel' });
        };
        window.errorCallback = function (error) {
          var message = 'Payment could not be opened.';
          try {
            if (error && typeof error === 'object') {
              message = error.explanation || error.message || error.cause || message;
            } else if (typeof error === 'string' && error) {
              message = error;
            }
          } catch (e) {}
          post({ type: 'error', stage: 'unknown', message: String(message) });
        };
        window.timeoutCallback = function () {
          post({
            type: 'error',
            stage: 'unknown',
            message: 'The payment session timed out. Please try again.',
          });
        };

        var scriptUrl = ${safeScript};
        var sessionId = ${safeSession};

        post({ type: 'stage', stage: 'script_loading' });
        setStatus('Loading secure payment…');

        var script = document.createElement('script');
        script.id = 'nmb-mpgs-checkout-sdk';
        script.async = true;
        script.src = scriptUrl;
        script.setAttribute('data-error', 'errorCallback');
        script.setAttribute('data-cancel', 'cancelCallback');
        script.setAttribute('data-complete', 'completeCallback');
        script.setAttribute('data-timeout', 'timeoutCallback');

        script.onload = function () {
          post({ type: 'stage', stage: 'script_loaded' });
          try {
            if (!window.Checkout) {
              post({
                type: 'error',
                stage: 'checkout_missing',
                message: 'Checkout object missing after script load.',
              });
              return;
            }
            post({ type: 'stage', stage: 'configure_started' });
            try {
              window.Checkout.configure({ session: { id: sessionId } });
            } catch (configureError) {
              post({
                type: 'error',
                stage: 'configure_failed',
                message: configureError && configureError.message
                  ? String(configureError.message)
                  : 'Checkout.configure failed.',
              });
              return;
            }
            post({ type: 'stage', stage: 'configure_success' });
            post({ type: 'stage', stage: 'show_started' });
            setStatus('Opening payment page…');
            try {
              window.Checkout.showPaymentPage();
            } catch (showError) {
              post({
                type: 'error',
                stage: 'show_payment_failed',
                message: showError && showError.message
                  ? String(showError.message)
                  : 'Checkout.showPaymentPage failed.',
              });
            }
          } catch (e) {
            post({
              type: 'error',
              stage: 'unknown',
              message: e && e.message ? String(e.message) : 'Unable to start payment.',
            });
          }
        };

        script.onerror = function () {
          post({
            type: 'error',
            stage: 'script_load',
            message: 'Failed to load Hosted Checkout script.',
          });
        };

        document.head.appendChild(script);
      })();
    </script>
  </body>
</html>`;
}

/**
 * Only app deep-link return finishes checkout — not gateway URLs with resultIndicator.
 */
export function isNmbAppPaymentReturnUrl(
  url: string,
  appScheme: string = env.appScheme,
): boolean {
  if (!url?.trim() || !appScheme?.trim()) return false;
  const scheme = appScheme.trim().toLowerCase();
  const normalized = url.trim();
  const prefix = `${scheme}://`;
  if (!normalized.toLowerCase().startsWith(prefix)) {
    return false;
  }
  const rest = normalized.slice(prefix.length);
  const path = rest.split('?')[0].split('#')[0].replace(/^\//, '').toLowerCase();
  return path === 'payment-return' || path.startsWith('payment-return/');
}

export function normalizeHcErrorStage(
  stage: string | null | undefined,
): NmbWebsiteCheckoutErrorStage {
  switch (stage) {
    case 'script_load':
    case 'checkout_missing':
    case 'configure_failed':
    case 'show_payment_failed':
    case 'navigation_failed':
    case 'unknown':
      return stage;
    // Legacy aliases from earlier Build 3 instrumentation
    case 'configure':
      return 'configure_failed';
    case 'show':
      return 'show_payment_failed';
    case 'runtime':
      return 'unknown';
    default:
      return 'unknown';
  }
}

/** Customer-safe copy — never forwards raw gateway internals. */
export function customerMessageForHcError(
  stage: NmbWebsiteCheckoutErrorStage,
): string {
  switch (stage) {
    case 'script_load':
      return 'Secure payment script could not load. Please retry.';
    case 'checkout_missing':
      return 'Secure payment SDK did not initialize. Please retry.';
    case 'configure_failed':
      return 'Secure payment could not be configured. Please retry.';
    case 'show_payment_failed':
      return 'Secure payment page could not open. Please retry.';
    case 'navigation_failed':
      return 'Secure payment page navigation failed. Please retry.';
    case 'unknown':
    default:
      return NMB_WEBSITE_HC_CUSTOMER_ERROR_RETRY;
  }
}

/**
 * Decide native reaction to a WebView postMessage.
 * Bootstrap/runtime errors must NOT complete the store (keeps modal open).
 */
export function resolveNmbWebsiteCheckoutMessageAction(
  raw: unknown,
  buildReturnUrl: (resultIndicator: string | null) => string,
): NmbWebsiteCheckoutMessageAction {
  if (!raw || typeof raw !== 'object') return { kind: 'ignore' };
  const payload = raw as NmbWebsiteCheckoutWebMessage;
  const type = typeof payload.type === 'string' ? payload.type : '';

  if (type === 'stage') {
    return { kind: 'ignore' };
  }

  if (type === 'complete') {
    const resultIndicator =
      typeof payload.resultIndicator === 'string' ? payload.resultIndicator : null;
    const returnUrl = buildReturnUrl(resultIndicator);
    return {
      kind: 'complete',
      result: {
        type: 'success',
        url: returnUrl,
        returnParams: extractNmbReturnParams(returnUrl),
      },
    };
  }

  if (type === 'cancel') {
    return {
      kind: 'complete',
      result: {
        type: 'cancel',
        url: null,
        returnParams: { ...EMPTY_RETURN },
      },
    };
  }

  if (type === 'error') {
    const stage = normalizeHcErrorStage(
      typeof payload.stage === 'string' ? payload.stage : null,
    );
    const rawMessage =
      typeof payload.message === 'string' ? payload.message : null;
    return {
      kind: 'show_error',
      stage,
      customerMessage: customerMessageForHcError(stage),
      diagnosticDetail: sanitizeHcDiagnosticDetail(rawMessage),
    };
  }

  return { kind: 'ignore' };
}

export function emptyNmbBrowserReturnParams(): NmbBrowserReturnParams {
  return { ...EMPTY_RETURN };
}

/** Assert HTML waits for script load before configure (regression guard). */
export function hostedCheckoutHtmlAwaitsScriptLoad(html: string): boolean {
  return (
    html.includes('script.onload') &&
    html.includes('Checkout.configure') &&
    html.includes('showPaymentPage') &&
    html.includes("stage: 'script_load'") &&
    html.includes("stage: 'checkout_missing'") &&
    html.includes("stage: 'configure_failed'") &&
    html.includes("stage: 'show_payment_failed'") &&
    !html.includes('<script\n      src=')
  );
}
