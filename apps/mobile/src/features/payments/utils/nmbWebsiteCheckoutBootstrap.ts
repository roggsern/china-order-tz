import { env } from '@/src/core/config';
import { extractNmbReturnParams } from './mapPayment';
import type { NmbBrowserReturnParams } from '../models/types';
import type { NmbBrowserSessionResult } from './nmbBrowser';

export type NmbWebsiteCheckoutPhase =
  | 'opening'
  | 'loading'
  | 'ready'
  | 'failed';

/**
 * Diagnostic stages — each maps to a real runtime boundary.
 *
 * Proven Build-3 device path for "unknown / Payment could not be opened.":
 * HTML window.errorCallback (MPGS data-error) previously hardcoded stage "unknown"
 * and default message "Payment could not be opened."
 */
export type NmbWebsiteCheckoutErrorStage =
  | 'html_bootstrap_not_started'
  | 'script_load'
  | 'checkout_missing'
  | 'configure_failed'
  | 'show_payment_failed'
  | 'mpgs_error'
  | 'mpgs_timeout'
  | 'webview_error'
  | 'webview_http_error'
  | 'navigation_failed'
  | 'message_parse_failed'
  | 'unknown';

export const NMB_HC_DIAGNOSTIC_STAGES: readonly NmbWebsiteCheckoutErrorStage[] = [
  'html_bootstrap_not_started',
  'script_load',
  'checkout_missing',
  'configure_failed',
  'show_payment_failed',
  'mpgs_error',
  'mpgs_timeout',
  'webview_error',
  'webview_http_error',
  'navigation_failed',
  'message_parse_failed',
  'unknown',
] as const;

export type NmbWebsiteCheckoutWebMessage = {
  type?: string;
  stage?: string | null;
  resultIndicator?: string | null;
  message?: string | null;
  lastMilestone?: string | null;
  host?: string | null;
  httpStatus?: number | null;
};

export type NmbWebsiteCheckoutMessageAction =
  | {
      kind: 'ignore';
      milestone?: string | null;
    }
  | {
      kind: 'show_error';
      stage: NmbWebsiteCheckoutErrorStage;
      customerMessage: string;
      diagnosticDetail: string;
      lastMilestone: string | null;
      host: string | null;
      httpStatus: number | null;
    }
  | { kind: 'complete'; result: NmbBrowserSessionResult };

const EMPTY_RETURN: NmbBrowserReturnParams = {
  resultIndicator: null,
  orderId: null,
  merchantReference: null,
  paymentTransactionId: null,
};

export const NMB_WEBSITE_HC_CUSTOMER_ERROR_RETRY =
  'Secure payment could not start. Please retry.';

/** Default string used by MPGS errorCallback when the SDK passes an empty error. */
export const MPGS_ERROR_CALLBACK_DEFAULT_MESSAGE = 'Payment could not be opened.';

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

/** Host only — never query/hash (may contain indicators). */
export function safeUrlHost(url: string | null | undefined): string | null {
  if (typeof url !== 'string' || !url.trim()) return null;
  try {
    const host = new URL(url.trim()).hostname;
    return host || null;
  } catch {
    return null;
  }
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
  text = text.replace(
    /\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/gi,
    '[redacted]',
  );
  text = text.replace(/\b[A-Za-z0-9_-]{32,}\b/g, '[redacted]');
  return text;
}

/**
 * Map MPGS data-error callback to a stage using the last bootstrap milestone.
 * This is the proven path that previously emitted stage "unknown".
 */
export function stageFromMpgsErrorCallback(
  lastMilestone: string | null | undefined,
): NmbWebsiteCheckoutErrorStage {
  switch (lastMilestone) {
    case 'bootstrap_started':
    case 'script_loading':
      return 'script_load';
    case 'script_loaded':
    case 'configure_started':
      return 'configure_failed';
    case 'configure_success':
    case 'show_started':
      return 'show_payment_failed';
    default:
      return 'mpgs_error';
  }
}

/**
 * Injected HTML: load Checkout.js via onload, then configure + show.
 * Posts diagnostic milestones and precise error stages (no secrets).
 */
export function buildHostedCheckoutHtml(
  sessionId: string,
  gatewayBaseUrl: string,
): string {
  const scriptUrl = buildNmbCheckoutScriptUrl(gatewayBaseUrl);
  const safeSession = JSON.stringify(sessionId);
  const safeScript = JSON.stringify(scriptUrl);
  const defaultMpgsMessage = JSON.stringify(MPGS_ERROR_CALLBACK_DEFAULT_MESSAGE);

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
        var lastMilestone = 'none';
        var DEFAULT_MPGS_MESSAGE = ${defaultMpgsMessage};

        function post(payload) {
          try {
            payload.lastMilestone = lastMilestone;
            if (window.ReactNativeWebView) {
              window.ReactNativeWebView.postMessage(JSON.stringify(payload));
            }
          } catch (e) {}
        }

        function setMilestone(name) {
          lastMilestone = name;
          post({ type: 'diagnostic', stage: name });
        }

        function setStatus(text) {
          try {
            var el = document.getElementById('status');
            if (el) el.textContent = text;
          } catch (e) {}
        }

        function extractMpgsMessage(error) {
          var message = DEFAULT_MPGS_MESSAGE;
          try {
            if (error && typeof error === 'object') {
              message =
                error.explanation ||
                error.message ||
                error.cause ||
                DEFAULT_MPGS_MESSAGE;
            } else if (typeof error === 'string' && error) {
              message = error;
            }
          } catch (e) {}
          return String(message);
        }

        // Earliest proof that injected HTML JS is executing inside the WebView.
        setMilestone('bootstrap_started');

        window.completeCallback = function (resultIndicator) {
          post({ type: 'complete', resultIndicator: resultIndicator || null });
        };
        window.cancelCallback = function () {
          post({ type: 'cancel' });
        };

        // Registered via data-error on Checkout.js — THIS was the Build-3 "unknown" path.
        window.errorCallback = function (error) {
          var stage = 'mpgs_error';
          if (lastMilestone === 'bootstrap_started' || lastMilestone === 'script_loading') {
            stage = 'script_load';
          } else if (
            lastMilestone === 'script_loaded' ||
            lastMilestone === 'configure_started'
          ) {
            stage = 'configure_failed';
          } else if (
            lastMilestone === 'configure_success' ||
            lastMilestone === 'show_started'
          ) {
            stage = 'show_payment_failed';
          }
          post({
            type: 'error',
            stage: stage,
            message: extractMpgsMessage(error),
            source: 'mpgs_data_error_callback',
          });
        };

        window.timeoutCallback = function () {
          post({
            type: 'error',
            stage: 'mpgs_timeout',
            message: 'The payment session timed out. Please try again.',
            source: 'mpgs_data_timeout_callback',
          });
        };

        var scriptUrl = ${safeScript};
        var sessionId = ${safeSession};

        setMilestone('script_loading');
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
          setMilestone('script_loaded');
          try {
            if (!window.Checkout) {
              post({
                type: 'error',
                stage: 'checkout_missing',
                message: 'Checkout object missing after script load.',
              });
              return;
            }
            setMilestone('configure_started');
            try {
              window.Checkout.configure({ session: { id: sessionId } });
            } catch (configureError) {
              post({
                type: 'error',
                stage: 'configure_failed',
                message: configureError && configureError.message
                  ? String(configureError.message)
                  : 'Checkout.configure threw.',
                source: 'configure_throw',
              });
              return;
            }
            setMilestone('configure_success');
            setMilestone('show_started');
            setStatus('Opening payment page…');
            try {
              window.Checkout.showPaymentPage();
            } catch (showError) {
              post({
                type: 'error',
                stage: 'show_payment_failed',
                message: showError && showError.message
                  ? String(showError.message)
                  : 'Checkout.showPaymentPage threw.',
                source: 'show_throw',
              });
            }
          } catch (e) {
            post({
              type: 'error',
              stage: 'unknown',
              message: e && e.message ? String(e.message) : 'Unable to start payment.',
              source: 'onload_catch',
            });
          }
        };

        script.onerror = function () {
          post({
            type: 'error',
            stage: 'script_load',
            message: 'Failed to load Hosted Checkout script.',
            source: 'script_onerror',
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
    case 'html_bootstrap_not_started':
    case 'script_load':
    case 'checkout_missing':
    case 'configure_failed':
    case 'show_payment_failed':
    case 'mpgs_error':
    case 'mpgs_timeout':
    case 'webview_error':
    case 'webview_http_error':
    case 'navigation_failed':
    case 'message_parse_failed':
    case 'unknown':
      return stage;
    case 'configure':
      return 'configure_failed';
    case 'show':
      return 'show_payment_failed';
    case 'runtime':
      return 'mpgs_error';
    default:
      return 'unknown';
  }
}

/** Customer-safe copy — never forwards raw gateway internals. */
export function customerMessageForHcError(
  stage: NmbWebsiteCheckoutErrorStage,
): string {
  switch (stage) {
    case 'html_bootstrap_not_started':
      return 'Secure payment page did not start. Please retry.';
    case 'script_load':
      return 'Secure payment script could not load. Please retry.';
    case 'checkout_missing':
      return 'Secure payment SDK did not initialize. Please retry.';
    case 'configure_failed':
      return 'Secure payment could not be configured. Please retry.';
    case 'show_payment_failed':
      return 'Secure payment page could not open. Please retry.';
    case 'mpgs_error':
      return 'Secure payment reported an error. Please retry.';
    case 'mpgs_timeout':
      return 'Secure payment timed out. Please retry.';
    case 'webview_error':
      return 'Secure payment browser failed to load. Please retry.';
    case 'webview_http_error':
      return 'Secure payment browser returned an HTTP error. Please retry.';
    case 'navigation_failed':
      return 'Secure payment page navigation failed. Please retry.';
    case 'message_parse_failed':
      return 'Secure payment sent an invalid message. Please retry.';
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
  const payload = raw as NmbWebsiteCheckoutWebMessage & { source?: string };
  const type = typeof payload.type === 'string' ? payload.type : '';
  const lastMilestone =
    typeof payload.lastMilestone === 'string' ? payload.lastMilestone : null;
  const host = typeof payload.host === 'string' ? payload.host : null;
  const httpStatus =
    typeof payload.httpStatus === 'number' ? payload.httpStatus : null;

  if (type === 'stage' || type === 'diagnostic') {
    return {
      kind: 'ignore',
      milestone: typeof payload.stage === 'string' ? payload.stage : lastMilestone,
    };
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
    let stage = normalizeHcErrorStage(
      typeof payload.stage === 'string' ? payload.stage : null,
    );
    // If HTML still sent legacy "unknown" from MPGS callback, reclassify by milestone.
    if (
      stage === 'unknown' &&
      (payload.source === 'mpgs_data_error_callback' ||
        (typeof payload.message === 'string' &&
          payload.message === MPGS_ERROR_CALLBACK_DEFAULT_MESSAGE))
    ) {
      stage = stageFromMpgsErrorCallback(lastMilestone);
    }
    const rawMessage =
      typeof payload.message === 'string' ? payload.message : null;
    const detailParts = [
      sanitizeHcDiagnosticDetail(rawMessage),
      lastMilestone ? `milestone=${lastMilestone}` : null,
      payload.source ? `source=${String(payload.source)}` : null,
    ].filter(Boolean);

    return {
      kind: 'show_error',
      stage,
      customerMessage: customerMessageForHcError(stage),
      diagnosticDetail: detailParts.join(' · '),
      lastMilestone,
      host,
      httpStatus,
    };
  }

  return { kind: 'ignore' };
}

export function emptyNmbBrowserReturnParams(): NmbBrowserReturnParams {
  return { ...EMPTY_RETURN };
}

/** Assert HTML waits for script load and emits bootstrap_started. */
export function hostedCheckoutHtmlAwaitsScriptLoad(html: string): boolean {
  return (
    html.includes("setMilestone('bootstrap_started')") &&
    html.includes("setMilestone('script_loaded')") &&
    html.includes('script.onload') &&
    html.includes('Checkout.configure') &&
    html.includes('showPaymentPage') &&
    html.includes("stage: 'script_load'") &&
    html.includes("stage: 'checkout_missing'") &&
    html.includes("stage: 'configure_failed'") &&
    html.includes("stage: 'show_payment_failed'") &&
    html.includes('mpgs_data_error_callback') &&
    !html.includes('<script\n      src=')
  );
}
