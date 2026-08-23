import { useLocalSearchParams } from 'expo-router';
import { ReturnRequestScreen } from '@/src/features/returns';
import { MissingRouteState } from '@/src/shared/components/MissingRouteState';

export default function OrderReturnRoute() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const orderId = Array.isArray(params.id) ? params.id[0] : params.id;

  if (!orderId?.trim()) {
    return (
      <MissingRouteState
        title="Return unavailable"
        message="This return link is missing. Open Orders to view your purchases."
        primaryLabel="Go to Orders"
        primaryHref="/(app)/(tabs)/orders"
      />
    );
  }

  return <ReturnRequestScreen orderId={orderId} />;
}
