import { z } from 'zod';

import { apiClient } from '@/src/core/api';

const lowStockItemSchema = z
  .object({
    variant_inventory_id: z.string().optional(),
    product_variant_id: z.string().optional(),
    sku: z.string().nullable().optional(),
    product_name: z.string().nullable().optional(),
    store_name: z.string().nullable().optional(),
    available: z.number().optional(),
    reorder_level: z.number().optional(),
    status: z.enum(['low_stock', 'out_of_stock']).optional(),
  })
  .passthrough();

export type LowStockItem = z.infer<typeof lowStockItemSchema>;

export function mapLowStockItems(raw: unknown): LowStockItem[] {
  const parsed = z.array(lowStockItemSchema).parse(raw);
  return parsed;
}

export async function fetchLowStock(): Promise<LowStockItem[]> {
  const response = await apiClient.get<unknown[]>('/admin/inventory/low-stock');
  return mapLowStockItems(response.data ?? []);
}
