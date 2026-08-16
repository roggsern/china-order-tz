import { z } from 'zod';

import { apiClient } from '@/src/core/api';
import type { PaginatedResponse, PaginationMeta } from '@/src/core/api/types';

export type CommerceChannelCode = 'CHINA_IMPORT' | 'TZ_LOCAL';

export type OrderListFilters = {
  status?: string;
  commerce_channel?: CommerceChannelCode;
  q?: string;
  page?: number;
};

export function buildOrdersQuery(filters: OrderListFilters): Record<string, string | number> {
  const query: Record<string, string | number> = {};
  if (filters.status && filters.status !== 'all') {
    query.status = filters.status;
  }
  if (filters.commerce_channel) {
    query.commerce_channel = filters.commerce_channel;
  }
  if (filters.q?.trim()) {
    query.q = filters.q.trim();
  }
  if (filters.page && filters.page > 1) {
    query.page = filters.page;
  }
  return query;
}

const orderUserSchema = z
  .object({
    id: z.string().optional(),
    name: z.string().nullable().optional(),
    email: z.string().nullable().optional(),
    phone: z.string().nullable().optional(),
  })
  .nullable()
  .optional();

const orderItemSchema = z
  .object({
    id: z.string().optional(),
    product_name: z.string().nullable().optional(),
    quantity: z.number().optional(),
    unit_price: z.number().optional(),
    line_total: z.number().optional(),
  })
  .passthrough();

const paymentSchema = z
  .object({
    id: z.string().optional(),
    status: z.string().optional(),
    amount: z.number().optional(),
    method: z.string().nullable().optional(),
  })
  .passthrough();

const orderSchema = z
  .object({
    id: z.string(),
    order_number: z.string(),
    commerce_channel_code: z.string().nullable().optional(),
    status: z.string(),
    status_label: z.string().optional(),
    total: z.number().optional(),
    grand_total: z.number().optional(),
    currency: z.string().optional(),
    user: orderUserSchema,
    items: z.array(orderItemSchema).optional(),
    payments: z.array(paymentSchema).optional(),
    fulfillment: z.unknown().optional(),
    refund_transactions: z.array(z.unknown()).optional(),
    created_at: z.string().optional(),
  })
  .passthrough();

export type AdminOrder = z.infer<typeof orderSchema>;

export function mapOrder(raw: unknown): AdminOrder {
  return orderSchema.parse(raw);
}

export function mapOrdersList(raw: unknown[]): AdminOrder[] {
  return raw.map(mapOrder);
}

function extractPaginationMeta(body: unknown): PaginationMeta {
  const meta = (body as { meta?: PaginationMeta }).meta;
  return {
    current_page: meta?.current_page ?? 1,
    last_page: meta?.last_page ?? 1,
    total: meta?.total ?? 0,
    per_page: meta?.per_page,
  };
}

export async function fetchOrders(filters: OrderListFilters): Promise<PaginatedResponse<AdminOrder>> {
  const query = buildOrdersQuery(filters);
  const response = await apiClient.get<AdminOrder[]>('/admin/orders', query);
  return {
    data: mapOrdersList((response.data as unknown[]) ?? []),
    meta: extractPaginationMeta(response),
  };
}

export async function fetchOrder(id: string): Promise<AdminOrder> {
  const response = await apiClient.get<unknown>(`/admin/orders/${id}`);
  return mapOrder(response.data);
}
