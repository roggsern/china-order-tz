import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { WebView } from 'react-native-webview';
import { env } from '@/src/core/config';
import { useNmbWebsiteCheckoutStore } from '../state/nmbWebsiteCheckoutStore';
import { extractNmbReturnParams } from '../utils/mapPayment';
import type { NmbBrowserSessionResult } from '../utils/nmbBrowser';
import { buildPaymentReturnRedirectUrl } from '../utils/nmbBrowser';

function buildHostedCheckoutHtml(sessionId: string, gatewayBaseUrl: string): string {
  const scriptUrl = `${gatewayBaseUrl.replace(/\/$/, '')}/static/checkout/checkout.min.js`;
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
    </style>
    <script>
      function post(payload) {
        if (window.ReactNativeWebView) {
          window.ReactNativeWebView.postMessage(JSON.stringify(payload));
        }
      }
      function completeCallback(resultIndicator) {
        post({ type: 'complete', resultIndicator: resultIndicator || null });
      }
      function cancelCallback() {
        post({ type: 'cancel' });
      }
      function errorCallback(error) {
        var message = 'Payment could not be opened.';
        try {
          if (error && typeof error === 'object') {
            message = error.explanation || error.message || error.cause || message;
          }
        } catch (e) {}
        post({ type: 'error', message: String(message) });
      }
      function timeoutCallback() {
        post({ type: 'error', message: 'The payment session timed out. Please try again.' });
      }
    </script>
    <script
      src=${safeScript}
      data-error="errorCallback"
      data-cancel="cancelCallback"
      data-complete="completeCallback"
      data-timeout="timeoutCallback"
    ></script>
    <script>
      (function () {
        try {
          if (!window.Checkout) {
            post({ type: 'error', message: 'Payment SDK failed to load.' });
            return;
          }
          window.Checkout.configure({ session: { id: ${safeSession} } });
          window.Checkout.showPaymentPage();
        } catch (e) {
          post({ type: 'error', message: e && e.message ? e.message : 'Unable to start payment.' });
        }
      })();
    </script>
  </head>
  <body>
    <p>Opening secure payment…</p>
  </body>
</html>`;
}

function finish(result: NmbBrowserSessionResult) {
  useNmbWebsiteCheckoutStore.getState().complete(result);
}

function maybeFinishFromUrl(url: string): boolean {
  const scheme = env.appScheme;
  const isAppReturn =
    url.startsWith(`${scheme}://`) ||
    /payment-return/i.test(url) ||
    /resultIndicator=/i.test(url);

  if (!isAppReturn) return false;

  const params = extractNmbReturnParams(url);
  finish({
    type: 'success',
    url,
    returnParams: params,
  });
  return true;
}

/**
 * Website Hosted Checkout (MPGS Checkout.js) when API returns session id without checkout_url.
 * Mirrors web NMB Website Hosted Checkout — does not invent payment status.
 */
export function NmbWebsiteCheckoutModal() {
  const request = useNmbWebsiteCheckoutStore((s) => s.request);

  if (!request) return null;

  const html = buildHostedCheckoutHtml(request.sessionId, request.gatewayBaseUrl);

  return (
    <Modal visible animationType="slide" onRequestClose={() => finish({
      type: 'cancel',
      url: null,
      returnParams: {
        resultIndicator: null,
        orderId: null,
        merchantReference: null,
        paymentTransactionId: null,
      },
    })}>
      <View style={styles.container}>
        <View style={styles.header}>
          <Text style={styles.title}>Secure payment</Text>
          <Pressable
            onPress={() =>
              finish({
                type: 'cancel',
                url: null,
                returnParams: {
                  resultIndicator: null,
                  orderId: null,
                  merchantReference: null,
                  paymentTransactionId: null,
                },
              })
            }
          >
            <Text style={styles.close}>Close</Text>
          </Pressable>
        </View>
        <WebView
          originWhitelist={['*']}
          source={{
            html,
            baseUrl: request.gatewayBaseUrl,
          }}
          javaScriptEnabled
          domStorageEnabled
          setSupportMultipleWindows={false}
          onMessage={(event) => {
            try {
              const payload = JSON.parse(event.nativeEvent.data) as {
                type?: string;
                resultIndicator?: string | null;
                message?: string;
              };
              if (payload.type === 'complete') {
                const returnUrl = `${buildPaymentReturnRedirectUrl()}?resultIndicator=${encodeURIComponent(
                  payload.resultIndicator ?? '',
                )}`;
                finish({
                  type: 'success',
                  url: returnUrl,
                  returnParams: extractNmbReturnParams(returnUrl),
                });
                return;
              }
              if (payload.type === 'cancel') {
                finish({
                  type: 'cancel',
                  url: null,
                  returnParams: {
                    resultIndicator: null,
                    orderId: null,
                    merchantReference: null,
                    paymentTransactionId: null,
                  },
                });
                return;
              }
              if (payload.type === 'error') {
                finish({
                  type: 'dismiss',
                  url: null,
                  returnParams: {
                    resultIndicator: null,
                    orderId: null,
                    merchantReference: null,
                    paymentTransactionId: null,
                  },
                });
              }
            } catch {
              // ignore malformed messages
            }
          }}
          onShouldStartLoadWithRequest={(req) => {
            if (maybeFinishFromUrl(req.url)) {
              return false;
            }
            return true;
          }}
          onNavigationStateChange={(nav) => {
            maybeFinishFromUrl(nav.url);
          }}
        />
      </View>
    </Modal>
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
});
