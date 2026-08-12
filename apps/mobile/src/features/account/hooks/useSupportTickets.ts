import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { AUTHENTICATED_QUERY_META, useAuthStore } from '@/src/core/auth';
import {
  createSupportTicket,
  fetchSupportTicket,
  fetchSupportTickets,
  replyToSupportTicket,
  type CreateSupportTicketInput,
} from '../api/supportApi';

export function supportTicketsQueryKey() {
  return ['account', 'support', 'tickets'] as const;
}

export function supportTicketQueryKey(ticketId: string) {
  return ['account', 'support', 'ticket', ticketId] as const;
}

export function useSupportTickets(enabled = true) {
  const authStatus = useAuthStore((s) => s.status);
  return useQuery({
    queryKey: supportTicketsQueryKey(),
    queryFn: fetchSupportTickets,
    enabled: enabled && authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function useSupportTicket(ticketId: string | null, enabled = true) {
  const authStatus = useAuthStore((s) => s.status);
  return useQuery({
    queryKey: supportTicketQueryKey(ticketId ?? ''),
    queryFn: () => fetchSupportTicket(ticketId!),
    enabled:
      Boolean(ticketId) && enabled && authStatus === 'authenticated',
    meta: AUTHENTICATED_QUERY_META,
  });
}

export function useSupportMutations() {
  const queryClient = useQueryClient();

  const create = useMutation({
    mutationFn: (input: CreateSupportTicketInput) => createSupportTicket(input),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: supportTicketsQueryKey() });
    },
  });

  const reply = useMutation({
    mutationFn: (input: { ticketId: string; message: string }) =>
      replyToSupportTicket(input.ticketId, input.message),
    onSuccess: (ticket) => {
      queryClient.setQueryData(supportTicketQueryKey(ticket.id), ticket);
      void queryClient.invalidateQueries({ queryKey: supportTicketsQueryKey() });
    },
  });

  return { create, reply };
}
