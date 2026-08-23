import { apiClient } from '@/src/core/api';
import type {
  CreateReturnInput,
  CustomerReturnRequest,
  CustomerReturnsPage,
} from '../models/types';
import {
  buildCreateReturnPayload,
  mapCustomerReturnRequest,
  mapCustomerReturnsPage,
} from '../utils/mapReturns';

/** GET /returns — authenticated customer return list. */
export async function fetchCustomerReturns(): Promise<CustomerReturnsPage> {
  const response = await apiClient.get<unknown>('/returns');
  return mapCustomerReturnsPage(response);
}

/** GET /returns/{id} */
export async function fetchCustomerReturn(
  returnId: string,
): Promise<CustomerReturnRequest> {
  const response = await apiClient.get<unknown>(
    `/returns/${encodeURIComponent(returnId)}`,
  );
  const mapped = mapCustomerReturnRequest(response.data);
  if (!mapped) {
    throw new Error('Return request response was empty.');
  }
  return mapped;
}

/** POST /orders/{order}/returns — backend eligibility is authoritative. */
export async function createCustomerReturn(
  input: CreateReturnInput,
): Promise<CustomerReturnRequest> {
  const response = await apiClient.post<unknown>(
    `/orders/${encodeURIComponent(input.orderId)}/returns`,
    buildCreateReturnPayload(input),
  );
  const mapped = mapCustomerReturnRequest(response.data);
  if (!mapped) {
    throw new Error('Return request response was empty.');
  }
  return mapped;
}
