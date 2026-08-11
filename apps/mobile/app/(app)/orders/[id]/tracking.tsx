import { useLocalSearchParams } from 'expo-router';
import { OrderTrackingScreen } from '@/src/features/orders';
import { MissingRouteState } from '@/src/shared/components/MissingRouteState';

export default function OrderTrackingRoute() {
  const params = useLocalSearchParams<{ id?: string | string[] }>();
  const orderId = Array.isArray(params.id) ? params.id[0] : params.id;

  if (!orderId?.trim()) {
    return (
      <MissingRouteState
        title="Tracking unavailable"
        message="This tracking link is missing an order. Open Orders to continue."
        primaryLabel="Go to Orders"
        primaryHref="/(app)/(tabs)/orders"
      />
    );
  }

  return <OrderTrackingScreen orderId={orderId} />;
}
