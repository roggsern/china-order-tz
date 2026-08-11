import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import { env } from '@/src/core/config';
import { useNmbWebsiteCheckoutStore } from '../state/nmbWebsiteCheckoutStore';
import type {
  NmbBrowserReturnParams,
  PaymentTransaction,
} from '../models/types';
import {
  canOpenCheckoutUrl,
  extractNmbReturnParams,
  isNmbWebsiteHostedCheckout,
} from './mapPayment';

export type NmbBrowserSessionResult = {
  type: 'success' | 'cancel' | 'dismiss' | 'locked' | string;
  url: string | null;
  returnParams: NmbBrowserReturnParams;
};

export function buildPaymentReturnRedirectUrl(): string {
  try {
    return Linking.createURL('payment-return', { scheme: env.appScheme });
  } catch {
    return `${env.appScheme}://payment-return`;
  }
}

function emptyReturnParams(): NmbBrowserReturnParams {
  return {
    resultIndicator: null,
    orderId: null,
    merchantReference: null,
    paymentTransactionId: null,
  };
}

/**
 * Opens NMB Hosted Checkout in an auth session browser (redirect checkout_url).
 * Return URL params are proof material for server reconcile only — never local paid.
 * Rejects non-HTTPS / non-allowlisted hosts before opening.
 */
export async function openNmbHostedCheckout(
  checkoutUrl: string,
): Promise<NmbBrowserSessionResult> {
  if (!canOpenCheckoutUrl(checkoutUrl)) {
    throw new Error('Payment service is unavailable. Please try again.');
  }

  const redirectUrl = buildPaymentReturnRedirectUrl();
  const result = await WebBrowser.openAuthSessionAsync(checkoutUrl, redirectUrl);

  if (result.type === 'success' && 'url' in result && typeof result.url === 'string') {
    return {
      type: result.type,
      url: result.url,
      returnParams: extractNmbReturnParams(result.url),
    };
  }

  return {
    type: result.type,
    url: null,
    returnParams: emptyReturnParams(),
  };
}

/**
 * Website Hosted Checkout when API returns session id without checkout_url.
 * Uses Checkout.js against the configured MPGS gateway (allowlisted HTTPS host).
 */
export async function openNmbWebsiteHostedCheckout(input: {
  sessionId: string;
  gatewayBaseUrl?: string;
}): Promise<NmbBrowserSessionResult> {
  const gatewayBaseUrl = (input.gatewayBaseUrl ?? env.nmbGatewayBaseUrl).replace(
    /\/$/,
    '',
  );
  if (!canOpenCheckoutUrl(`${gatewayBaseUrl}/static/checkout/checkout.min.js`)) {
    throw new Error(
      'Payment gateway is not configured for this app build. Please try again later.',
    );
  }
  if (!input.sessionId.trim()) {
    throw new Error('NMB did not return a checkout session. Please try again.');
  }

  if (__DEV__) {
    // Sanitized diagnostics only — no secrets.
    console.info('[nmb] website_hosted_checkout', {
      stage: 'open_sdk',
      host: new URL(gatewayBaseUrl).hostname,
      sessionIdPrefix: input.sessionId.slice(0, 12),
    });
  }

  return useNmbWebsiteCheckoutStore.getState().open({
    sessionId: input.sessionId.trim(),
    gatewayBaseUrl,
  });
}

/**
 * Launch redirect URL or Website Hosted Checkout from a payment transaction.
 */
export async function launchNmbCheckoutForTransaction(
  transaction: PaymentTransaction,
): Promise<NmbBrowserSessionResult> {
  if (canOpenCheckoutUrl(transaction.checkoutUrl)) {
    if (__DEV__) {
      try {
        console.info('[nmb] redirect_checkout', {
          stage: 'open_auth_session',
          host: new URL(transaction.checkoutUrl!).hostname,
        });
      } catch {
        // ignore
      }
    }
    return openNmbHostedCheckout(transaction.checkoutUrl!);
  }

  if (isNmbWebsiteHostedCheckout(transaction)) {
    return openNmbWebsiteHostedCheckout({
      sessionId: transaction.providerReference!,
    });
  }

  if (__DEV__) {
    console.info('[nmb] checkout_unavailable', {
      stage: 'missing_url_and_session',
      hasCheckoutUrl: Boolean(transaction.checkoutUrl),
      hasProviderReference: Boolean(transaction.providerReference),
      provider: transaction.provider,
    });
  }

  throw new Error(
    'NMB checkout is not ready yet. Please retry in a moment or contact support if this continues.',
  );
}
