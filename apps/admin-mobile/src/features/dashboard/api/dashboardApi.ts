import { z } from 'zod';

import { apiClient } from '@/src/core/api';
import type { PaginationMeta } from '@/src/core/api/types';

const overviewSchema = z
  .object({
    orders_today: z.number().optional(),
    revenue_today: z.number().optional(),
    paid_orders_today: z.number().optional(),
    pending_actions: z.number().optional(),
    customers_total: z.number().optional(),
    new_customers: z.number().optional(),
  })
  .passthrough();

const attentionItemSchema = z
  .object({
    key: z.string().optional(),
    label: z.string().optional(),
    count: z.number().optional(),
    severity: z.string().optional(),
  })
  .passthrough();

const pipelineSchema = z.record(z.string(), z.unknown()).optional();

const operationsSchema = z
  .object({
    fulfillment_queue: z
      .object({
        total: z.number().optional(),
        china: z.number().optional(),
        local: z.number().optional(),
      })
      .optional(),
    warehouse: z.unknown().optional(),
    shipments: z.unknown().optional(),
    open_returns: z.number().optional(),
  })
  .passthrough()
  .optional();

const dashboardDataSchema = z.object({
  overview: overviewSchema.optional(),
  attention_items: z.array(attentionItemSchema).optional(),
  china_pipeline: pipelineSchema,
  tz_local: pipelineSchema,
  operations: operationsSchema,
});

export type AdminDashboard = z.infer<typeof dashboardDataSchema>;

export function mapDashboardResponse(raw: unknown): AdminDashboard {
  return dashboardDataSchema.parse(raw);
}

export async function fetchDashboard(): Promise<AdminDashboard> {
  const envelope = await apiClient.get<unknown>('/admin/dashboard');
  return mapDashboardResponse(envelope.data);
}

const alertSchema = z
  .object({
    severity: z.string(),
    title: z.string(),
    message: z.string(),
    source: z.string(),
    created_at: z.string().optional(),
  })
  .passthrough();

const alertsDataSchema = z.object({
  period: z.object({ from: z.string(), to: z.string() }).optional(),
  generated_at: z.string().optional(),
  counts: z
    .object({
      operational: z.number().optional(),
      growth: z.number().optional(),
      total: z.number().optional(),
    })
    .optional(),
  alerts: z.array(alertSchema).optional(),
});

export type AdminAlerts = z.infer<typeof alertsDataSchema>;

export function mapAlertsResponse(raw: unknown): AdminAlerts {
  return alertsDataSchema.parse(raw);
}

export async function fetchAlerts(): Promise<AdminAlerts> {
  const envelope = await apiClient.get<unknown>('/admin/alerts');
  return mapAlertsResponse(envelope.data);
}

export type { PaginationMeta };
