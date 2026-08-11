import { useCallback, useState } from 'react';
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
  sanitizeHcDiagnosticDetail,
  type NmbWebsiteCheckoutErrorStage,
  type NmbWebsiteCheckoutPhase,
} from '../utils/nmbWebsiteCheckoutBootstrap';
import type { NmbWebsiteCheckoutRequest } from '../state/nmbWebsiteCheckoutStore';
import { extractNmbReturnParams } from '../utils/mapPayment';
import type { NmbBrowserSessionResult } from '../utils/nmbBrowser';
import { buildPaymentReturnRedirectUrl } from '../utils/nmbBrowser';

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

function gatewayHost(gatewayBaseUrl: string): string | null {
  try {
    return new URL(gatewayBaseUrl).hostname;
  } catch {
    return null;
  }
}

type BodyProps = {
  request: NmbWebsiteCheckoutRequest;
};

/**
 * Keyed body so phase/error reset when a new session request opens.
 * Temporary diagnostic stage is shown in the failed UI for device verification.
 */
function NmbWebsiteCheckoutModalBody({ request }: BodyProps) {
  const [phase, setPhase] = useState<NmbWebsiteCheckoutPhase>('loading');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [diagnosticStage, setDiagnosticStage] =
    useState<NmbWebsiteCheckoutErrorStage | null>(null);
  const [diagnosticDetail, setDiagnosticDetail] = useState<string | null>(null);
  const [webviewKey, setWebviewKey] = useState(0);

  const showFailure = useCallback(
    (
      stage: NmbWebsiteCheckoutErrorStage,
      customerMessage: string,
      detail: string,
    ) => {
      logNmbHcStage('webview_error', { stage });
      setDiagnosticStage(stage);
      setDiagnosticDetail(detail);
      setErrorMessage(customerMessage);
      setPhase('failed');
    },
    [],
  );

  const tryFinishFromUrl = useCallback(
    (url: string): boolean => {
      if (!isNmbAppPaymentReturnUrl(url, env.appScheme)) {
        logNmbHcStage('navigation', { host: gatewayHost(url) });
        return false;
      }
      logNmbHcStage('close_reason', { type: 'app_return' });
      finish({
        type: 'success',
        url,
        returnParams: extractNmbReturnParams(url),
      });
      return true;
    },
    [],
  );

  const html = buildHostedCheckoutHtml(request.sessionId, request.gatewayBaseUrl);
  const showWebView = phase !== 'failed';

  return (
    <Modal
      visible
      animationType="slide"
      onRequestClose={() => finish(cancelResult())}
    >
      <View style={styles.container}>
        <View style={styles.header}>
          <Text style={styles.title}>Secure payment</Text>
          <Pressable onPress={() => finish(cancelResult())}>
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
            </View>
            <Text style={styles.errorHint}>
              Your payment is still processing on the server. Nothing was marked paid
              in the app.
            </Text>
            <Pressable
              style={styles.retryButton}
              onPress={() => {
                setErrorMessage(null);
                setDiagnosticStage(null);
                setDiagnosticDetail(null);
                setPhase('loading');
                setWebviewKey((k) => k + 1);
                logNmbHcStage('script_loading', { retry: true });
              }}
            >
              <Text style={styles.retryButtonText}>Retry secure payment</Text>
            </Pressable>
            <Pressable
              style={styles.secondaryButton}
              onPress={() => finish(cancelResult())}
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
                host: gatewayHost(request.gatewayBaseUrl),
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
                  const stage =
                    payload &&
                    typeof payload === 'object' &&
                    typeof (payload as { stage?: unknown }).stage === 'string'
                      ? (payload as { stage: string }).stage
                      : null;
                  if (stage) {
                    logNmbHcStage(stage);
                    if (stage === 'script_loaded' || stage === 'show_started') {
                      setPhase('ready');
                    }
                  }
                  return;
                }

                if (action.kind === 'show_error') {
                  showFailure(
                    action.stage,
                    action.customerMessage,
                    action.diagnosticDetail,
                  );
                  return;
                }

                finish(action.result);
              } catch {
                showFailure(
                  'unknown',
                  'Secure payment could not start. Please retry.',
                  'Malformed WebView message.',
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
              const desc = syntheticEvent?.nativeEvent?.description;
              showFailure(
                'navigation_failed',
                'Secure payment page navigation failed. Please retry.',
                sanitizeHcDiagnosticDetail(
                  typeof desc === 'string' && desc.trim()
                    ? desc.trim()
                    : 'WebView onError',
                ),
              );
            }}
            onHttpError={(syntheticEvent) => {
              const status = syntheticEvent?.nativeEvent?.statusCode;
              showFailure(
                'navigation_failed',
                'Secure payment page navigation failed. Please retry.',
                sanitizeHcDiagnosticDetail(
                  typeof status === 'number'
                    ? `HTTP ${status}`
                    : 'WebView onHttpError',
                ),
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
 * Bootstrap errors keep the modal open with customer + diagnostic stage copy.
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
