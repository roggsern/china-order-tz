import { apiClient } from '@/src/core/api';
import type {
  DeliveryOptionShow,
  DeliveryOptionSnapshot,
  SelectDeliveryOptionInput,
  UpdateDeliveryOptionInput,
} from '../models/deliveryOption';
import {
  buildSelectDeliveryOptionPayload,
  buildUpdateDeliveryOptionPayload,
  mapDeliveryOptionShow,
  mapDeliveryOptionSnapshot,
} from '../utils/mapDeliveryOption';

/**
 * GET /orders/{order}/delivery-option — post-pay handoff state.
 * Distinct from checkout POST /checkout/{id}/shipping-choice.
 */
export async function fetchDeliveryOption(orderId: string): Promise<DeliveryOptionShow> {
  const response = await apiClient.get<unknown>(
    `/orders/${encodeURIComponent(orderId)}/delivery-option`,
  );
  return mapDeliveryOptionShow(response.data);
}

/** POST /orders/{order}/delivery-option — legacy/missing option only. */
export async function selectDeliveryOption(
  input: SelectDeliveryOptionInput,
): Promise<DeliveryOptionSnapshot | null> {
  const response = await apiClient.post<unknown>(
    `/orders/${encodeURIComponent(input.orderId)}/delivery-option`,
    buildSelectDeliveryOptionPayload(input),
  );
  return mapDeliveryOptionSnapshot(response.data);
}

/** PATCH /orders/{order}/delivery-option — agent notes / confirm. Does not change paid amount. */
export async function updateDeliveryOption(
  input: UpdateDeliveryOptionInput,
): Promise<DeliveryOptionSnapshot | null> {
  const response = await apiClient.patch<unknown>(
    `/orders/${encodeURIComponent(input.orderId)}/delivery-option`,
    buildUpdateDeliveryOptionPayload(input),
  );
  return mapDeliveryOptionSnapshot(response.data);
}
