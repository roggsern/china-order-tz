import Constants from 'expo-constants';

/**
 * Typed mobile environment config owner.
 * All feature code must read API base URL from here — never hardcode hosts.
 *
 * NMB payment handoff authority:
 * - Backend creates the payment session and returns checkout_url and/or session id.
 * - Mobile opens the returned URL (allowlisted) or the merchant web launcher.
 * - Mobile never constructs MPGS/NMB gateway URLs and does not embed gateway secrets.
 */
export type MobileEnv = {
  apiBaseUrl: string;
  appScheme: string;
  /**
   * Merchant web app origin for NMB Website Hosted Checkout launcher
   * (`/payments/{transactionId}/nmb`). Opened in the system browser.
   */
  webAppBaseUrl: string;
  /** Exact hostnames allowed for NMB / payment hosted checkout. */
  paymentCheckoutAllowedHosts: string[];
  /** Hostname suffixes allowed (e.g. .gateway.mastercard.com). */
  paymentCheckoutAllowedHostSuffixes: string[];
  /**
   * When true, sandbox NMB/MPGS hosts may appear on the checkout allowlist.
   * Production builds must keep this false so sandbox checkout URLs cannot open.
   */
  allowNmbSandboxCheckout: boolean;
};

export const PRODUCTION_API_BASE_URL = 'https://api.chinaordertz.com/api/v1';

export const MISSING_PRODUCTION_API_URL_MESSAGE =
  'EXPO_PUBLIC_API_BASE_URL (or app.json extra.apiBaseUrl) must be set to a non-localhost API for production builds.';

/** Default storefront origin — NMB Website Hosted Checkout launcher host. */
export const DEFAULT_WEB_APP_BASE_URL = 'https://chinaordertz.com';

/** Sandbox MPGS / NMB host — allowlisted only for dev or explicit preview opt-in. */
export const NMB_SANDBOX_GATEWAY_HOST = 'test-nmbbank.mtf.gateway.mastercard.com';

function readExtra(key: string): string | undefined {
  const extra = Constants.expoConfig?.extra as Record<string, unknown> | undefined;
  const value = extra?.[key];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : undefined;
}

function isDevRuntime(isDev?: boolean): boolean {
  return (
    isDev ??
    (typeof __DEV__ !== 'undefined' ? __DEV__ : process.env.NODE_ENV !== 'production')
  );
}

export function isLoopbackApiUrl(url: string): boolean {
  try {
    const host = new URL(url).hostname.toLowerCase();
    return (
      host === 'localhost' ||
      host === '127.0.0.1' ||
      host === '0.0.0.0' ||
      host === '10.0.2.2' ||
      host === '[::1]' ||
      host === '::1'
    );
  } catch {
    return true;
  }
}

/**
 * Resolve API base URL.
 * Development may fall back to localhost.
 * Production-like builds fail fast on missing/loopback URLs.
 */
export function resolveApiBaseUrl(input?: {
  fromProcess?: string | undefined;
  fromExtra?: string | undefined;
  isDev?: boolean;
}): string {
  const fromProcess = (input?.fromProcess ?? process.env.EXPO_PUBLIC_API_BASE_URL)?.trim();
  const fromExtra = (input?.fromExtra ?? readExtra('apiBaseUrl'))?.trim();
  const isDev = isDevRuntime(input?.isDev);

  const candidate = (fromProcess || fromExtra || '').replace(/\/$/, '');

  if (isDev) {
    return candidate || 'http://localhost:8000/api/v1';
  }

  if (!candidate || isLoopbackApiUrl(candidate)) {
    throw new Error(MISSING_PRODUCTION_API_URL_MESSAGE);
  }

  return candidate;
}

function splitCsv(value: string | undefined): string[] {
  if (!value?.trim()) return [];
  return value
    .split(',')
    .map((part) => part.trim().toLowerCase())
    .filter(Boolean);
}

function parseTruthyFlag(value: string | undefined): boolean | null {
  if (value === undefined) return null;
  const normalized = value.trim().toLowerCase();
  if (!normalized) return null;
  if (['1', 'true', 'yes', 'on'].includes(normalized)) return true;
  if (['0', 'false', 'no', 'off'].includes(normalized)) return false;
  return null;
}

/**
 * Sandbox checkout hosts are allowed in:
 * - development runtime (__DEV__)
 * - explicit EXPO_PUBLIC_ALLOW_NMB_SANDBOX_CHECKOUT=true (EAS preview)
 *
 * Production must not opt in — otherwise sandbox gateway URLs could open.
 */
export function resolveAllowNmbSandboxCheckout(input?: {
  fromProcess?: string;
  fromExtra?: string;
  isDev?: boolean;
}): boolean {
  const isDev = isDevRuntime(input?.isDev);
  if (isDev) {
    return true;
  }

  const fromProcess = parseTruthyFlag(
    input?.fromProcess ?? process.env.EXPO_PUBLIC_ALLOW_NMB_SANDBOX_CHECKOUT,
  );
  if (fromProcess !== null) {
    return fromProcess;
  }

  const fromExtra = parseTruthyFlag(
    input?.fromExtra ?? readExtra('allowNmbSandboxCheckout'),
  );
  if (fromExtra !== null) {
    return fromExtra;
  }

  return false;
}

/**
 * Default allowlist — production Mastercard / NMB gateway hosts.
 * Sandbox host is added only when {@link resolveAllowNmbSandboxCheckout} is true.
 * Extend via EXPO_PUBLIC_PAYMENT_CHECKOUT_ALLOWED_HOSTS (comma-separated).
 * No secrets — hostnames only.
 */
const PRODUCTION_PAYMENT_CHECKOUT_HOSTS = [
  'nmbbank.mtf.gateway.mastercard.com',
  'ap.gateway.mastercard.com',
  'eu.gateway.mastercard.com',
  'na.gateway.mastercard.com',
];

/** Dev/mock-only hosts — never included in production release allowlists. */
const DEV_ONLY_PAYMENT_CHECKOUT_HOSTS = ['checkout.nmb.test'];

const DEFAULT_PAYMENT_CHECKOUT_HOST_SUFFIXES = [
  '.gateway.mastercard.com',
  '.mtf.gateway.mastercard.com',
];

export function resolvePaymentCheckoutAllowedHosts(input?: {
  fromProcess?: string;
  fromExtra?: string;
  isDev?: boolean;
  allowSandbox?: boolean;
}): string[] {
  const isDev = isDevRuntime(input?.isDev);
  const allowSandbox =
    input?.allowSandbox ??
    resolveAllowNmbSandboxCheckout({
      isDev,
    });
  const fromEnv = splitCsv(
    input?.fromProcess ?? process.env.EXPO_PUBLIC_PAYMENT_CHECKOUT_ALLOWED_HOSTS,
  );
  const fromExtra = splitCsv(
    input?.fromExtra ?? readExtra('paymentCheckoutAllowedHosts'),
  );
  const base = [
    ...PRODUCTION_PAYMENT_CHECKOUT_HOSTS,
    ...(allowSandbox ? [NMB_SANDBOX_GATEWAY_HOST] : []),
    ...(isDev ? DEV_ONLY_PAYMENT_CHECKOUT_HOSTS : []),
    ...fromEnv,
    ...fromExtra,
  ];
  return [...new Set(base)];
}

function resolvePaymentCheckoutAllowedHostSuffixes(): string[] {
  const fromEnv = splitCsv(
    process.env.EXPO_PUBLIC_PAYMENT_CHECKOUT_ALLOWED_HOST_SUFFIXES,
  );
  const fromExtra = splitCsv(readExtra('paymentCheckoutAllowedHostSuffixes'));
  return [
    ...new Set([
      ...DEFAULT_PAYMENT_CHECKOUT_HOST_SUFFIXES,
      ...fromEnv,
      ...fromExtra,
    ]),
  ];
}

/**
 * Merchant web origin for `/payments/{id}/nmb` launcher (system browser).
 */
export function resolveWebAppBaseUrl(input?: {
  fromProcess?: string;
  fromExtra?: string;
}): string {
  const fromProcess = (
    input?.fromProcess ?? process.env.EXPO_PUBLIC_WEB_APP_BASE_URL
  )?.trim();
  const fromExtra = (input?.fromExtra ?? readExtra('webAppBaseUrl'))?.trim();
  return (fromProcess || fromExtra || DEFAULT_WEB_APP_BASE_URL).replace(/\/$/, '');
}

export const env: MobileEnv = {
  apiBaseUrl: resolveApiBaseUrl(),
  appScheme: Constants.expoConfig?.scheme?.toString() ?? 'chinaordertz',
  webAppBaseUrl: resolveWebAppBaseUrl(),
  allowNmbSandboxCheckout: resolveAllowNmbSandboxCheckout(),
  paymentCheckoutAllowedHosts: resolvePaymentCheckoutAllowedHosts(),
  paymentCheckoutAllowedHostSuffixes: resolvePaymentCheckoutAllowedHostSuffixes(),
};
