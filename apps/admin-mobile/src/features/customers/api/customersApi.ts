import { z } from 'zod';

import { apiClient } from '@/src/core/api';
import { optionalMoneySchema } from '@/src/core/api/money';
import type { PaginatedResponse, PaginationMeta } from '@/src/core/api/types';

const customerSchema = z
  .object({
    id: z.string(),
    customer_code: z.string().optional(),
    name: z.string().nullable().optional(),
    email: z.string().nullable().optional(),
    phone: z.string().nullable().optional(),
    lifecycle_status: z.string().optional(),
    is_active: z.boolean().optional(),
    metrics: z
      .object({
        total_orders: z.number().optional(),
        total_spend: optionalMoneySchema,
        last_order_at: z.string().nullable().optional(),
      })
      .optional(),
  })
  .passthrough();

export type AdminCustomer = z.infer<typeof customerSchema>;

export function mapCustomer(raw: unknown): AdminCustomer {
  return customerSchema.parse(raw);
}

export function mapCustomers(raw: unknown[]): AdminCustomer[] {
  return raw.map(mapCustomer);
}

export type CustomerListFilters = {
  search?: string;
  page?: number;
  per_page?: number;
};

/**
 * Backend CustomerProfileService filters on `search` (not `q`).
 * @see apps/api/app/Services/Crm/CustomerProfileService.php
 */
export function buildCustomersQuery(filters: CustomerListFilters): Record<string, string | number> {
  const page = filters.page ?? 1;
  const perPage = filters.per_page ?? 20;
  const query: Record<string, string | number> = { page, per_page: perPage };
  if (filters.search?.trim()) {
    query.search = filters.search.trim();
  }
  return query;
}

function extractMeta(body: unknown): PaginationMeta {
  const meta = (body as { meta?: PaginationMeta }).meta;
  return {
    current_page: meta?.current_page ?? 1,
    last_page: meta?.last_page ?? 1,
    total: meta?.total ?? 0,
    per_page: meta?.per_page,
  };
}

export async function fetchCustomers(
  search?: string,
  page = 1,
): Promise<PaginatedResponse<AdminCustomer>> {
  const query = buildCustomersQuery({ search, page, per_page: 20 });

  const response = await apiClient.get<AdminCustomer[]>('/admin/customers', query);
  return {
    data: mapCustomers((response.data as unknown[]) ?? []),
    meta: extractMeta(response),
  };
}

export async function fetchCustomer(id: string): Promise<AdminCustomer> {
  const response = await apiClient.get<unknown>(`/admin/customers/${id}`);
  return mapCustomer(response.data);
}
