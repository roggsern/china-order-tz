import { z } from 'zod';

import { apiClient } from '@/src/core/api';
import type { PaginatedResponse, PaginationMeta } from '@/src/core/api/types';

const messageSchema = z
  .object({
    id: z.string(),
    ticket_id: z.string().optional(),
    sender_type: z.string().optional(),
    message: z.string(),
    created_at: z.string().optional(),
  })
  .passthrough();

const ticketSchema = z
  .object({
    id: z.string(),
    ticket_number: z.string(),
    subject: z.string(),
    status: z.string().optional(),
    status_label: z.string().optional(),
    priority: z.string().optional(),
    priority_label: z.string().optional(),
    category: z.string().optional(),
    category_label: z.string().optional(),
    customer: z
      .object({
        id: z.string().optional(),
        name: z.string().nullable().optional(),
        email: z.string().nullable().optional(),
      })
      .nullable()
      .optional(),
    assigned_admin: z
      .object({
        id: z.string().optional(),
        name: z.string().optional(),
      })
      .nullable()
      .optional(),
    messages: z.array(messageSchema).optional(),
    created_at: z.string().optional(),
  })
  .passthrough();

export type SupportTicket = z.infer<typeof ticketSchema>;
export type SupportMessage = z.infer<typeof messageSchema>;

export function mapSupportTicket(raw: unknown): SupportTicket {
  return ticketSchema.parse(raw);
}

export function mapSupportTickets(raw: unknown[]): SupportTicket[] {
  return raw.map(mapSupportTicket);
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

export async function fetchSupportTickets(page = 1): Promise<PaginatedResponse<SupportTicket>> {
  const response = await apiClient.get<SupportTicket[]>('/admin/support/tickets', {
    page,
    per_page: 20,
  });
  return {
    data: mapSupportTickets((response.data as unknown[]) ?? []),
    meta: extractMeta(response),
  };
}

export async function fetchSupportTicket(id: string): Promise<SupportTicket> {
  const response = await apiClient.get<unknown>(`/admin/support/tickets/${id}`);
  return mapSupportTicket(response.data);
}

export type ReplyPayload = {
  message: string;
  waiting_for_customer?: boolean;
};

export function buildReplyPayload(message: string, waitingForCustomer = false): ReplyPayload {
  return {
    message: message.trim(),
    waiting_for_customer: waitingForCustomer,
  };
}

export async function replyToTicket(id: string, payload: ReplyPayload): Promise<SupportTicket> {
  const response = await apiClient.post<unknown>(
    `/admin/support/tickets/${id}/messages`,
    payload,
  );
  return mapSupportTicket(response.data);
}
