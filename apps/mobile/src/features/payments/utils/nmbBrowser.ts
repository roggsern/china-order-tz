import * as Linking from 'expo-linking';
import * as WebBrowser from 'expo-web-browser';
import { env } from '@/src/core/config';
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
  type: 'success' | 'cancel' | 'dismiss' | string;
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

function mapAuthSessionResult(
  result: WebBrowser.WebBrowserAuthSessionResult,
): NmbBrowserSessionResult {
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
 * True when URL is HTTPS on the configured merchant web app host
 * (chinaordertz.com launcher — not the MPGS gateway).
 */
export function canOpenNmbWebLauncherUrl(url: string | null | undefined): boolean {
  if (!url?.trim()) return false;
  try {
    const parsed = new URL(url.trim());
    if (parsed.protocol !== 'https:') return false;
    const host = parsed.hostname.toLowerCase();
    const allowed = new URL(env.webAppBaseUrl).hostname.toLowerCase();
    if (!host || !allowed) return false;
    if (host === allowed) return true;
    if (allowed.startsWith('www.') && host === allowed.slice(4)) return true;
    if (!allowed.startsWith('www.') && host === `www.${allowed}`) return true;
    return false;
  } catch {
    return false;
  }
}

/**
 * Existing web NMB Website Hosted Checkout launcher.
 * Passes sessionId so the page can run Checkout.js without a web login cookie.
 * mobileReturn=1 asks the web app to hand off to chinaordertz://payment-return.
 */
export function buildNmbWebHostedCheckoutLauncherUrl(input: {
  paymentTransactionId: string;
  sessionId?: string | null;
  successIndicator?: string | null;
  webAppBaseUrl?: string;
}): string {
  const base = (input.webAppBaseUrl ?? env.webAppBaseUrl).replace(/\/$/, '');
  const txnId = input.paymentTransactionId.trim();
  if (!txnId) {
    throw new Error('Payment transaction is required to open NMB checkout.');
  }

  const url = new URL(`${base}/payments/${encodeURIComponent(txnId)}/nmb`);
  const sessionId = input.sessionId?.trim();
  if (sessionId) {
    url.searchParams.set('sessionId', sessionId);
  }
  const successIndicator = input.successIndicator?.trim();
  if (successIndicator) {
    url.searchParams.set('successIndicator', successIndicator);
  }
  url.searchParams.set('mobileReturn', '1');
  return url.toString();
}

/**
 * Opens an allowlisted HTTPS URL in an auth session browser.
 * Return URL params are proof material for server reconcile only — never local paid.
 */
export async function openNmbHostedCheckout(
  checkoutUrl: string,
): Promise<NmbBrowserSessionResult> {
  if (!canOpenCheckoutUrl(checkoutUrl) && !canOpenNmbWebLauncherUrl(checkoutUrl)) {
    throw new Error('Payment service is unavailable. Please try again.');
  }

  const redirectUrl = buildPaymentReturnRedirectUrl();
  const result = await WebBrowser.openAuthSessionAsync(checkoutUrl, redirectUrl);
  return mapAuthSessionResult(result);
}

/**
 * Website Hosted Checkout via the working web launcher in the system browser.
 * Replaces the previous in-app WebView + Checkout.js bootstrap.
 */
export async function openNmbWebsiteHostedCheckout(input: {
  paymentTransactionId: string;
  sessionId: string;
  successIndicator?: string | null;
}): Promise<NmbBrowserSessionResult> {
  if (!input.paymentTransactionId.trim()) {
    throw new Error('Payment transaction is required to open NMB checkout.');
  }
  if (!input.sessionId.trim()) {
    throw new Error('NMB did not return a checkout session. Please try again.');
  }

  const launcherUrl = buildNmbWebHostedCheckoutLauncherUrl({
    paymentTransactionId: input.paymentTransactionId,
    sessionId: input.sessionId,
    successIndicator: input.successIndicator,
  });

  if (!canOpenNmbWebLauncherUrl(launcherUrl)) {
    throw new Error(
      'Payment website is not configured for this app build. Please try again later.',
    );
  }

  if (__DEV__) {
    try {
      console.info('[nmb] website_hosted_checkout', {
        stage: 'open_web_launcher',
        host: new URL(launcherUrl).hostname,
        path: '/payments/.../nmb',
      });
    } catch {
      // ignore
    }
  }

  return openNmbHostedCheckout(launcherUrl);
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
      paymentTransactionId: transaction.id,
      sessionId: transaction.providerReference!,
      successIndicator: transaction.successIndicator,
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
