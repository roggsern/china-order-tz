import { useLocalSearchParams } from 'expo-router';
import { ReturnDetailScreen } from '@/src/features/returns';
import { MissingRouteState } from '@/src/shared/components/MissingRouteState';

export default function AccountReturnDetailRoute() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const returnId = Array.isArray(params.id) ? params.id[0] : params.id;

  if (!returnId?.trim()) {
    return (
      <MissingRouteState
        title="Return unavailable"
        message="This return link is missing. Open Returns to view your requests."
        primaryLabel="Go to Returns"
        primaryHref="/(app)/account/returns"
      />
    );
  }

  return <ReturnDetailScreen returnId={returnId} />;
}
