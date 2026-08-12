import { apiClient } from '@/src/core/api';

export const SUPPORT_TICKET_CATEGORIES = [
  { value: 'order_issue', label: 'Order Issue' },
  { value: 'payment_issue', label: 'Payment Issue' },
  { value: 'delivery_issue', label: 'Delivery Issue' },
  { value: 'product_issue', label: 'Product Issue' },
  { value: 'return_issue', label: 'Return Issue' },
  { value: 'general', label: 'General' },
] as const;

export type SupportTicketCategoryValue =
  (typeof SUPPORT_TICKET_CATEGORIES)[number]['value'];

export type SupportMessage = {
  id: string;
  message: string;
  senderType: string | null;
  createdAt: string | null;
};

export type SupportTicket = {
  id: string;
  ticketNumber: string | null;
  subject: string;
  category: string | null;
  categoryLabel: string | null;
  priority: string | null;
  priorityLabel: string | null;
  status: string | null;
  statusLabel: string | null;
  orderId: string | null;
  createdAt: string | null;
  messages: SupportMessage[];
};

export type CreateSupportTicketInput = {
  subject: string;
  category: SupportTicketCategoryValue;
  message: string;
  priority?: string;
  order_id?: string | null;
};

function asRecord(value: unknown): Record<string, unknown> {
  return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function stringField(data: Record<string, unknown>, key: string): string | null {
  const value = data[key];
  return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

function mapMessage(raw: unknown): SupportMessage | null {
  const data = asRecord(raw);
  const id =
    typeof data.id === 'string' || typeof data.id === 'number'
      ? String(data.id)
      : '';
  const message = stringField(data, 'message');
  if (!id || !message) return null;
  return {
    id,
    message,
    senderType: stringField(data, 'sender_type'),
    createdAt: stringField(data, 'created_at'),
  };
}

export function mapSupportTicket(raw: unknown): SupportTicket | null {
  const data = asRecord(raw);
  const id =
    typeof data.id === 'string' || typeof data.id === 'number'
      ? String(data.id)
      : '';
  const subject = stringField(data, 'subject');
  if (!id || !subject) return null;

  const messagesRaw = Array.isArray(data.messages) ? data.messages : [];

  return {
    id,
    ticketNumber: stringField(data, 'ticket_number'),
    subject,
    category: stringField(data, 'category'),
    categoryLabel: stringField(data, 'category_label'),
    priority: stringField(data, 'priority'),
    priorityLabel: stringField(data, 'priority_label'),
    status: stringField(data, 'status'),
    statusLabel: stringField(data, 'status_label'),
    orderId: stringField(data, 'order_id'),
    createdAt: stringField(data, 'created_at'),
    messages: messagesRaw
      .map(mapMessage)
      .filter((row): row is SupportMessage => row !== null),
  };
}

export async function fetchSupportTickets(): Promise<SupportTicket[]> {
  const response = await apiClient.get<unknown>('/account/support/tickets');
  const rows = Array.isArray(response.data) ? response.data : [];
  return rows
    .map(mapSupportTicket)
    .filter((row): row is SupportTicket => row !== null);
}

export async function fetchSupportTicket(
  ticketId: string,
): Promise<SupportTicket> {
  const response = await apiClient.get<unknown>(
    `/account/support/tickets/${encodeURIComponent(ticketId)}`,
  );
  const ticket = mapSupportTicket(response.data);
  if (!ticket) {
    throw new Error('Support ticket response was empty.');
  }
  return ticket;
}

export async function createSupportTicket(
  input: CreateSupportTicketInput,
): Promise<SupportTicket> {
  const response = await apiClient.post<unknown>(
    '/account/support/tickets',
    input,
  );
  const ticket = mapSupportTicket(response.data);
  if (!ticket) {
    throw new Error('Created support ticket response was empty.');
  }
  return ticket;
}

export async function replyToSupportTicket(
  ticketId: string,
  message: string,
): Promise<SupportTicket> {
  const response = await apiClient.post<unknown>(
    `/account/support/tickets/${encodeURIComponent(ticketId)}/messages`,
    { message },
  );
  const ticket = mapSupportTicket(response.data);
  if (!ticket) {
    throw new Error('Support reply response was empty.');
  }
  return ticket;
}
