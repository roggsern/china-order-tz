import { useLocalSearchParams } from 'expo-router';
import { OrderDetailScreen } from '@/src/features/orders';
import { MissingRouteState } from '@/src/shared/components/MissingRouteState';

export default function OrderDetailRoute() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const orderId = Array.isArray(params.id) ? params.id[0] : params.id;

  if (!orderId?.trim()) {
    return (
      <MissingRouteState
        title="Order unavailable"
        message="This order link is missing. Open Orders to view your purchases."
        primaryLabel="Go to Orders"
        primaryHref="/(app)/(tabs)/orders"
      />
    );
  }

  return <OrderDetailScreen orderId={orderId} />;
}
