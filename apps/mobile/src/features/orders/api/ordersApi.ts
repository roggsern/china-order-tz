import { apiClient } from '@/src/core/api';
import type {
  CancelOrderInput,
  OrderDetail,
  OrderTracking,
  OrdersListFilter,
  OrdersListPage,
  ReceivingChoiceSnapshot,
  SelectReceivingMethodInput,
} from '../models/types';
import {
  buildCancelOrderPayload,
  buildReceivingMethodPayload,
  mapOrderDetail,
  mapOrderTracking,
  mapOrdersListPage,
  mapReceivingChoiceSnapshot,
  normalizeOrdersFilter,
} from '../utils/mapOrders';

export type FetchOrdersParams = {
  filter?: OrdersListFilter;
  page?: number;
  perPage?: number;
};

/**
 * GET /orders — authenticated Contract v1 paginated list.
 */
export async function fetchOrders(
  params: FetchOrdersParams = {},
): Promise<OrdersListPage> {
  const filter = normalizeOrdersFilter(params.filter);
  const response = await apiClient.get<unknown>('/orders', {
    filter,
    page: params.page ?? 1,
    per_page: params.perPage ?? 10,
  });

  return mapOrdersListPage(response);
}

/**
 * GET /orders/{order} — authenticated detail.
 */
export async function fetchOrderDetail(orderId: string): Promise<OrderDetail> {
  const response = await apiClient.get<unknown>(
    `/orders/${encodeURIComponent(orderId)}`,
  );
  return mapOrderDetail(response.data);
}

/**
 * GET /orders/{order}/tracking — authenticated tracking timeline.
 */
export async function fetchOrderTracking(orderId: string): Promise<OrderTracking> {
  const response = await apiClient.get<unknown>(
    `/orders/${encodeURIComponent(orderId)}/tracking`,
  );
  return mapOrderTracking(response.data);
}

/**
 * POST /orders/{order}/cancel — server decides eligibility.
 */
export async function cancelOrder(input: CancelOrderInput): Promise<OrderDetail> {
  const response = await apiClient.post<unknown>(
    `/orders/${encodeURIComponent(input.orderId)}/cancel`,
    buildCancelOrderPayload(input.reason),
  );
  return mapOrderDetail(response.data);
}

/**
 * POST /orders/{order}/receiving-method — post-arrival China company shipping only.
 * Distinct from checkout shipping-choice and from delivery-option CRUD.
 */
export async function selectReceivingMethod(
  input: SelectReceivingMethodInput,
): Promise<ReceivingChoiceSnapshot | null> {
  const response = await apiClient.post<unknown>(
    `/orders/${encodeURIComponent(input.orderId)}/receiving-method`,
    buildReceivingMethodPayload(input.receivingMethod),
  );
  const data = response.data && typeof response.data === 'object'
    ? (response.data as Record<string, unknown>)
    : {};
  return mapReceivingChoiceSnapshot(data.receiving_choice);
}
