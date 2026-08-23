import { apiClient } from '@/src/core/api';
import type {
  ApplyShippingChoiceInput,
  CheckoutDeliveryAddress,
  CheckoutPrepare,
  CheckoutSession,
  DeliveryAddressInput,
} from '../models/types';
import {
  isCheckoutSessionAlreadyGone,
} from '../utils/cancelCheckoutSession';
import {
  buildDeliveryAddressPayload,
  buildShippingChoicePayload,
  mapCheckoutPrepare,
  mapCheckoutSession,
  mapDeliveryAddress,
} from '../utils/mapCheckout';

/** POST /checkout/prepare — address + cart preview (no order). */
export async function prepareCheckout(): Promise<CheckoutPrepare> {
  const response = await apiClient.post<unknown>('/checkout/prepare');
  return mapCheckoutPrepare(response.data);
}

/** GET /checkout — same prepare surface. */
export async function fetchCheckoutPreview(): Promise<CheckoutPrepare> {
  const response = await apiClient.get<unknown>('/checkout');
  return mapCheckoutPrepare(response.data);
}

/** POST /checkout/start — creates checkout session (201). */
export async function startCheckoutSession(): Promise<CheckoutSession> {
  const response = await apiClient.post<unknown>('/checkout/start', {});
  return mapCheckoutSession(response.data);
}

/** GET /checkout/{id} */
export async function fetchCheckoutSession(
  sessionId: string,
): Promise<CheckoutSession> {
  const response = await apiClient.get<unknown>(
    `/checkout/${encodeURIComponent(sessionId)}`,
  );
  return mapCheckoutSession(response.data);
}

/** POST /checkout/{id}/refresh */
export async function refreshCheckoutSession(
  sessionId: string,
): Promise<CheckoutSession> {
  const response = await apiClient.post<unknown>(
    `/checkout/${encodeURIComponent(sessionId)}/refresh`,
  );
  return mapCheckoutSession(response.data);
}

/** POST /checkout/{id}/shipping-choice */
export async function applyCheckoutShippingChoice(
  sessionId: string,
  input: ApplyShippingChoiceInput,
): Promise<CheckoutSession> {
  const response = await apiClient.post<unknown>(
    `/checkout/${encodeURIComponent(sessionId)}/shipping-choice`,
    buildShippingChoicePayload(input),
  );
  return mapCheckoutSession(response.data);
}

/**
 * DELETE /checkout/{id} — cancels an unfinished server session.
 * Does not cancel an already-created order. Completed sessions return 422.
 */
export async function cancelCheckoutSession(sessionId: string): Promise<void> {
  await apiClient.delete(`/checkout/${encodeURIComponent(sessionId)}`);
}

/**
 * Treat already-cancelled / missing sessions as a successful abandon.
 * Completed-session 422 is not swallowed.
 */
export async function cancelCheckoutSessionSafely(
  sessionId: string,
): Promise<'cancelled' | 'already_gone'> {
  try {
    await cancelCheckoutSession(sessionId);
    return 'cancelled';
  } catch (error) {
    if (isCheckoutSessionAlreadyGone(error)) {
      return 'already_gone';
    }
    throw error;
  }
}

/** PATCH /profile/address — required before prepare when address missing. */
export async function updateDeliveryAddress(
  input: DeliveryAddressInput,
): Promise<CheckoutDeliveryAddress> {
  const response = await apiClient.patch<unknown>(
    '/profile/address',
    buildDeliveryAddressPayload(input),
  );
  return mapDeliveryAddress(response.data);
}
