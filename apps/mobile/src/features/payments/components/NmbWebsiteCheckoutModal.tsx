import { useCallback, useRef, useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { env } from '@/src/core/config';
import { useNmbWebsiteCheckoutStore } from '../state/nmbWebsiteCheckoutStore';
import {
  buildHostedCheckoutHtml,
  emptyNmbBrowserReturnParams,
  isNmbAppPaymentReturnUrl,
  logNmbHcStage,
  resolveNmbWebsiteCheckoutMessageAction,
  safeUrlHost,
  sanitizeHcDiagnosticDetail,
  type NmbWebsiteCheckoutErrorStage,
  type NmbWebsiteCheckoutPhase,
} from '../utils/nmbWebsiteCheckoutBootstrap';
import type { NmbWebsiteCheckoutRequest } from '../state/nmbWebsiteCheckoutStore';
import { extractNmbReturnParams } from '../utils/mapPayment';
import type { NmbBrowserSessionResult } from '../utils/nmbBrowser';
import { buildPaymentReturnRedirectUrl } from '../utils/nmbBrowser';

const BOOTSTRAP_WATCHDOG_MS = 4000;

function finish(result: NmbBrowserSessionResult) {
  logNmbHcStage('close_reason', { type: result.type });
  useNmbWebsiteCheckoutStore.getState().complete(result);
}

function cancelResult(): NmbBrowserSessionResult {
  return {
    type: 'cancel',
    url: null,
    returnParams: emptyNmbBrowserReturnParams(),
  };
}

type BodyProps = {
  request: NmbWebsiteCheckoutRequest;
};

/**
 * Keyed body so phase/error reset when a new session request opens.
 * Temporary diagnostics identify the exact HC failure boundary on-device.
 */
function NmbWebsiteCheckoutModalBody({ request }: BodyProps) {
  const [phase, setPhase] = useState<NmbWebsiteCheckoutPhase>('loading');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [diagnosticStage, setDiagnosticStage] =
    useState<NmbWebsiteCheckoutErrorStage | null>(null);
  const [diagnosticDetail, setDiagnosticDetail] = useState<string | null>(null);
  const [diagnosticHost, setDiagnosticHost] = useState<string | null>(null);
  const [diagnosticHttpStatus, setDiagnosticHttpStatus] = useState<number | null>(
    null,
  );
  const [bootstrapStarted, setBootstrapStarted] = useState(false);
  const [scriptLoaded, setScriptLoaded] = useState(false);
  const [lastMilestone, setLastMilestone] = useState<string | null>(null);
  const [webviewKey, setWebviewKey] = useState(0);

  const bootstrapStartedRef = useRef(false);
  const failedRef = useRef(false);
  const watchdogRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const clearWatchdog = useCallback(() => {
    if (watchdogRef.current) {
      clearTimeout(watchdogRef.current);
      watchdogRef.current = null;
    }
  }, []);

  const showFailure = useCallback(
    (
      stage: NmbWebsiteCheckoutErrorStage,
      customerMessage: string,
      detail: string,
      extras?: {
        host?: string | null;
        httpStatus?: number | null;
        lastMilestone?: string | null;
      },
    ) => {
      if (failedRef.current) return;
      failedRef.current = true;
      clearWatchdog();
      logNmbHcStage('webview_error', { stage });
      setDiagnosticStage(stage);
      setDiagnosticDetail(detail);
      setDiagnosticHost(extras?.host ?? null);
      setDiagnosticHttpStatus(
        typeof extras?.httpStatus === 'number' ? extras.httpStatus : null,
      );
      if (extras?.lastMilestone) {
        setLastMilestone(extras.lastMilestone);
      }
      setErrorMessage(customerMessage);
      setPhase('failed');
    },
    [clearWatchdog],
  );

  const armBootstrapWatchdog = useCallback(() => {
    clearWatchdog();
    watchdogRef.current = setTimeout(() => {
      if (!bootstrapStartedRef.current && !failedRef.current) {
        showFailure(
          'html_bootstrap_not_started',
          'Secure payment page did not start. Please retry.',
          'Injected HTML JS did not post bootstrap_started.',
          { host: safeUrlHost(request.gatewayBaseUrl) },
        );
      }
    }, BOOTSTRAP_WATCHDOG_MS);
  }, [clearWatchdog, request.gatewayBaseUrl, showFailure]);

  const tryFinishFromUrl = useCallback(
    (url: string): boolean => {
      if (!isNmbAppPaymentReturnUrl(url, env.appScheme)) {
        logNmbHcStage('navigation', { host: safeUrlHost(url) });
        return false;
      }
      clearWatchdog();
      logNmbHcStage('close_reason', { type: 'app_return' });
      finish({
        type: 'success',
        url,
        returnParams: extractNmbReturnParams(url),
      });
      return true;
    },
    [clearWatchdog],
  );

  const resetDiagnostics = useCallback(() => {
    failedRef.current = false;
    bootstrapStartedRef.current = false;
    setErrorMessage(null);
    setDiagnosticStage(null);
    setDiagnosticDetail(null);
    setDiagnosticHost(null);
    setDiagnosticHttpStatus(null);
    setBootstrapStarted(false);
    setScriptLoaded(false);
    setLastMilestone(null);
    setPhase('loading');
  }, []);

  const html = buildHostedCheckoutHtml(request.sessionId, request.gatewayBaseUrl);
  const showWebView = phase !== 'failed';

  return (
    <Modal
      visible
      animationType="slide"
      onRequestClose={() => {
        clearWatchdog();
        finish(cancelResult());
      }}
    >
      <View style={styles.container}>
        <View style={styles.header}>
          <Text style={styles.title}>Secure payment</Text>
          <Pressable
            onPress={() => {
              clearWatchdog();
              finish(cancelResult());
            }}
          >
            <Text style={styles.close}>Close</Text>
          </Pressable>
        </View>

        {phase === 'failed' ? (
          <View style={styles.errorBox}>
            <Text style={styles.errorTitle}>Secure payment unavailable</Text>
            <Text style={styles.errorBody}>
              {errorMessage ?? 'Unable to open secure payment. Please retry.'}
            </Text>
            <View style={styles.diagnosticBox}>
              <Text style={styles.diagnosticLabel}>Diagnostic (temporary)</Text>
              <Text style={styles.diagnosticStage}>
                stage: {diagnosticStage ?? 'unknown'}
              </Text>
              <Text style={styles.diagnosticDetail}>
                {diagnosticDetail ?? 'No additional detail.'}
              </Text>
              <Text style={styles.diagnosticMeta}>
                bootstrap_started: {bootstrapStarted ? 'yes' : 'no'}
              </Text>
              <Text style={styles.diagnosticMeta}>
                script_loaded: {scriptLoaded ? 'yes' : 'no'}
              </Text>
              <Text style={styles.diagnosticMeta}>
                last_milestone: {lastMilestone ?? 'none'}
              </Text>
              <Text style={styles.diagnosticMeta}>
                host: {diagnosticHost ?? safeUrlHost(request.gatewayBaseUrl) ?? 'n/a'}
              </Text>
              <Text style={styles.diagnosticMeta}>
                http_status:{' '}
                {diagnosticHttpStatus != null ? String(diagnosticHttpStatus) : 'n/a'}
              </Text>
            </View>
            <Text style={styles.errorHint}>
              Your payment is still processing on the server. Nothing was marked paid
              in the app.
            </Text>
            <Pressable
              style={styles.retryButton}
              onPress={() => {
                resetDiagnostics();
                setWebviewKey((k) => k + 1);
                logNmbHcStage('script_loading', { retry: true });
              }}
            >
              <Text style={styles.retryButtonText}>Retry secure payment</Text>
            </Pressable>
            <Pressable
              style={styles.secondaryButton}
              onPress={() => {
                clearWatchdog();
                finish(cancelResult());
              }}
            >
              <Text style={styles.secondaryButtonText}>Back to payment</Text>
            </Pressable>
          </View>
        ) : (
          <Text style={styles.status}>
            {phase === 'ready'
              ? 'Complete payment on the secure page.'
              : 'Opening secure payment…'}
          </Text>
        )}

        {showWebView ? (
          <WebView
            key={webviewKey}
            style={styles.webview}
            originWhitelist={['*']}
            source={{
              html,
              baseUrl: request.gatewayBaseUrl,
            }}
            javaScriptEnabled
            domStorageEnabled
            setSupportMultipleWindows={false}
            onLoadStart={() => {
              logNmbHcStage('script_loading', {
                host: safeUrlHost(request.gatewayBaseUrl),
              });
              armBootstrapWatchdog();
            }}
            onLoadEnd={() => {
              logNmbHcStage('webview_load_end', {
                host: safeUrlHost(request.gatewayBaseUrl),
                bootstrapStarted: bootstrapStartedRef.current,
              });
            }}
            onMessage={(event) => {
              try {
                const payload = JSON.parse(event.nativeEvent.data) as unknown;
                const action = resolveNmbWebsiteCheckoutMessageAction(
                  payload,
                  (resultIndicator) =>
                    `${buildPaymentReturnRedirectUrl()}?resultIndicator=${encodeURIComponent(
                      resultIndicator ?? '',
                    )}`,
                );

                if (action.kind === 'ignore') {
                  const milestone = action.milestone;
                  if (milestone) {
                    setLastMilestone(milestone);
                    logNmbHcStage(milestone);
                    if (milestone === 'bootstrap_started') {
                      bootstrapStartedRef.current = true;
                      setBootstrapStarted(true);
                      clearWatchdog();
                    }
                    if (milestone === 'script_loaded') {
                      setScriptLoaded(true);
                      setPhase('ready');
                    }
                    if (milestone === 'show_started') {
                      setPhase('ready');
                    }
                  }
                  return;
                }

                if (action.kind === 'show_error') {
                  if (action.lastMilestone === 'bootstrap_started') {
                    bootstrapStartedRef.current = true;
                    setBootstrapStarted(true);
                  }
                  if (
                    action.lastMilestone === 'script_loaded' ||
                    action.stage === 'checkout_missing' ||
                    action.stage === 'configure_failed' ||
                    action.stage === 'show_payment_failed' ||
                    action.stage === 'mpgs_error'
                  ) {
                    setScriptLoaded(true);
                  }
                  showFailure(
                    action.stage,
                    action.customerMessage,
                    action.diagnosticDetail,
                    {
                      host: action.host ?? safeUrlHost(request.gatewayBaseUrl),
                      httpStatus: action.httpStatus,
                      lastMilestone: action.lastMilestone,
                    },
                  );
                  return;
                }

                clearWatchdog();
                finish(action.result);
              } catch {
                showFailure(
                  'message_parse_failed',
                  'Secure payment sent an invalid message. Please retry.',
                  'Malformed WebView message.',
                  { host: safeUrlHost(request.gatewayBaseUrl) },
                );
              }
            }}
            onShouldStartLoadWithRequest={(req) => {
              if (tryFinishFromUrl(req.url)) {
                return false;
              }
              return true;
            }}
            onNavigationStateChange={(nav) => {
              tryFinishFromUrl(nav.url);
            }}
            onError={(syntheticEvent) => {
              const nativeEvent = syntheticEvent?.nativeEvent;
              const desc = nativeEvent?.description;
              const url = typeof nativeEvent?.url === 'string' ? nativeEvent.url : null;
              showFailure(
                'webview_error',
                'Secure payment browser failed to load. Please retry.',
                sanitizeHcDiagnosticDetail(
                  typeof desc === 'string' && desc.trim()
                    ? desc.trim()
                    : 'WebView onError',
                ),
                { host: safeUrlHost(url) ?? safeUrlHost(request.gatewayBaseUrl) },
              );
            }}
            onHttpError={(syntheticEvent) => {
              const nativeEvent = syntheticEvent?.nativeEvent;
              const status = nativeEvent?.statusCode;
              const url = typeof nativeEvent?.url === 'string' ? nativeEvent.url : null;
              showFailure(
                'webview_http_error',
                'Secure payment browser returned an HTTP error. Please retry.',
                sanitizeHcDiagnosticDetail(
                  typeof status === 'number'
                    ? `HTTP ${status}`
                    : 'WebView onHttpError',
                ),
                {
                  host: safeUrlHost(url) ?? safeUrlHost(request.gatewayBaseUrl),
                  httpStatus: typeof status === 'number' ? status : null,
                },
              );
            }}
          />
        ) : null}
      </View>
    </Modal>
  );
}

/**
 * Website Hosted Checkout (MPGS Checkout.js) when API returns session id without checkout_url.
 * Bootstrap/SDK errors keep the modal open with precise diagnostic stage copy.
 */
export function NmbWebsiteCheckoutModal() {
  const request = useNmbWebsiteCheckoutStore((s) => s.request);

  if (!request) return null;

  return (
    <NmbWebsiteCheckoutModalBody
      key={`${request.sessionId}:${request.gatewayBaseUrl}`}
      request={request}
    />
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: '#fff', paddingTop: 48 },
  header: {
    paddingHorizontal: 16,
    paddingBottom: 12,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  title: { fontSize: 17, fontWeight: '700', color: '#111' },
  close: { color: '#0a7ea4', fontWeight: '600', fontSize: 15 },
  status: {
    paddingHorizontal: 16,
    paddingBottom: 8,
    fontSize: 13,
    color: '#666',
  },
  webview: { flex: 1 },
  errorBox: {
    flex: 1,
    paddingHorizontal: 20,
    paddingTop: 24,
  },
  errorTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: '#111',
    marginBottom: 10,
  },
  errorBody: {
    fontSize: 15,
    color: '#333',
    lineHeight: 22,
    marginBottom: 12,
  },
  diagnosticBox: {
    backgroundColor: '#f4f4f5',
    borderRadius: 8,
    padding: 12,
    marginBottom: 16,
  },
  diagnosticLabel: {
    fontSize: 11,
    fontWeight: '700',
    color: '#666',
    textTransform: 'uppercase',
    marginBottom: 6,
  },
  diagnosticStage: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111',
    fontFamily: 'monospace',
    marginBottom: 6,
  },
  diagnosticDetail: {
    fontSize: 13,
    color: '#444',
    lineHeight: 18,
    marginBottom: 8,
  },
  diagnosticMeta: {
    fontSize: 12,
    color: '#555',
    fontFamily: 'monospace',
    marginBottom: 2,
  },
  errorHint: {
    fontSize: 13,
    color: '#666',
    lineHeight: 20,
    marginBottom: 24,
  },
  retryButton: {
    backgroundColor: '#0a7ea4',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
    marginBottom: 12,
  },
  retryButtonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  secondaryButton: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingVertical: 14,
    alignItems: 'center',
  },
  secondaryButtonText: { color: '#333', fontWeight: '600', fontSize: 15 },
});
