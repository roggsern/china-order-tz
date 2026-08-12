import { useLocalSearchParams } from 'expo-router';
import { SupportTicketDetailScreen } from '@/src/features/account/components/SupportTicketDetailScreen';
import { EmptyState } from '@/src/shared/ui/EmptyState';

export default function AccountSupportTicketRoute() {
  const params = useLocalSearchParams<{ id?: string }>();
  const ticketId = typeof params.id === 'string' ? params.id : null;

  if (!ticketId) {
    return (
      <EmptyState
        title="Ticket missing"
        message="This support ticket link is incomplete."
      />
    );
  }

  return <SupportTicketDetailScreen ticketId={ticketId} />;
}
