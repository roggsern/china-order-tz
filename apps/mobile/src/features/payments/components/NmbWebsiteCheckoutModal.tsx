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
 */
function NmbWebsiteCheckoutModalBody({ request }: BodyProps) {
  const [phase, setPhase] = useState<NmbWebsiteCheckoutPhase>('loading');
  const [errorMessage, setErrorMessage] = useState<string | null>(null);
  const [webviewKey, setWebviewKey] = useState(0);

  const tryFinishFromUrl = useCallback((url: string): boolean => {
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
  }, []);

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
            <Text style={styles.errorHint}>
              Your payment is still processing on the server. Nothing was marked paid
              in the app.
            </Text>
            <Pressable
              style={styles.retryButton}
              onPress={() => {
                setErrorMessage(null);
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
                  logNmbHcStage('webview_error', { stage: action.stage });
                  setPhase('failed');
                  setErrorMessage(action.customerMessage);
                  return;
                }

                finish(action.result);
              } catch {
                // ignore malformed messages
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
            onError={() => {
              logNmbHcStage('webview_error', { stage: 'runtime' });
              setPhase('failed');
              setErrorMessage('Unable to open secure payment. Please retry.');
            }}
            onHttpError={() => {
              logNmbHcStage('webview_error', { stage: 'runtime' });
              setPhase('failed');
              setErrorMessage('Unable to open secure payment. Please retry.');
            }}
          />
        ) : null}
      </View>
    </Modal>
  );
}

/**
 * Website Hosted Checkout (MPGS Checkout.js) when API returns session id without checkout_url.
 * Bootstrap errors keep the modal open with customer copy — never silent dismiss.
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
