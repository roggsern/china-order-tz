import Constants from 'expo-constants';

/**
 * Typed mobile environment config owner.
 * All feature code must read API base URL from here — never hardcode hosts.
 */
export type MobileEnv = {
  apiBaseUrl: string;
  appScheme: string;
  /** MPGS / NMB gateway origin used for Website Hosted Checkout SDK. */
  nmbGatewayBaseUrl: string;
  /** Exact hostnames allowed for NMB / payment hosted checkout. */
  paymentCheckoutAllowedHosts: string[];
  /** Hostname suffixes allowed (e.g. .gateway.mastercard.com). */
  paymentCheckoutAllowedHostSuffixes: string[];
};

export const PRODUCTION_API_BASE_URL = 'https://api.chinaordertz.com/api/v1';

export const MISSING_PRODUCTION_API_URL_MESSAGE =
  'EXPO_PUBLIC_API_BASE_URL (or app.json extra.apiBaseUrl) must be set to a non-localhost API for production builds.';

/** Default MPGS sandbox gateway (Website Hosted Checkout SDK host). */
export const DEFAULT_NMB_GATEWAY_BASE_URL =
  'https://test-nmbbank.mtf.gateway.mastercard.com';

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

/**
 * Default allowlist — Mastercard Hosted Checkout / NMB gateway hosts.
 * Extend via EXPO_PUBLIC_PAYMENT_CHECKOUT_ALLOWED_HOSTS (comma-separated).
 * No secrets — hostnames only.
 */
const DEFAULT_PAYMENT_CHECKOUT_HOSTS = [
  'test-nmbbank.mtf.gateway.mastercard.com',
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
}): string[] {
  const isDev = isDevRuntime(input?.isDev);
  const fromEnv = splitCsv(
    input?.fromProcess ?? process.env.EXPO_PUBLIC_PAYMENT_CHECKOUT_ALLOWED_HOSTS,
  );
  const fromExtra = splitCsv(
    input?.fromExtra ?? readExtra('paymentCheckoutAllowedHosts'),
  );
  const base = [
    ...DEFAULT_PAYMENT_CHECKOUT_HOSTS,
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
 * Gateway origin for Checkout.js (Website Hosted Checkout).
 * Must be HTTPS and allowlisted.
 */
export function resolveNmbGatewayBaseUrl(input?: {
  fromProcess?: string;
  fromExtra?: string;
}): string {
  const fromProcess = (
    input?.fromProcess ?? process.env.EXPO_PUBLIC_NMB_GATEWAY_URL
  )?.trim();
  const fromExtra = (input?.fromExtra ?? readExtra('nmbGatewayBaseUrl'))?.trim();
  const candidate = (fromProcess || fromExtra || DEFAULT_NMB_GATEWAY_BASE_URL).replace(
    /\/$/,
    '',
  );
  return candidate;
}

export const env: MobileEnv = {
  apiBaseUrl: resolveApiBaseUrl(),
  appScheme: Constants.expoConfig?.scheme?.toString() ?? 'chinaordertz',
  nmbGatewayBaseUrl: resolveNmbGatewayBaseUrl(),
  paymentCheckoutAllowedHosts: resolvePaymentCheckoutAllowedHosts(),
  paymentCheckoutAllowedHostSuffixes: resolvePaymentCheckoutAllowedHostSuffixes(),
};
