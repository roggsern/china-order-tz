import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { OrderTimeline } from '../components/OrderTimeline';
import { useOrderTracking } from '../hooks/useOrders';
import { getOrderErrorMessage } from '../utils/orderErrorMessage';
import { buildOrderTrackingHref } from '../utils/orderRoutes';

type Props = {
  orderId: string;
};

export function OrderTrackingScreen({ orderId }: Props) {
  const authStatus = useAuthStore((s) => s.status);
  const trackingQuery = useOrderTracking(orderId);

  if (authStatus !== 'authenticated') {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Tracking</Text>
        <Text style={styles.body}>Please sign in to view tracking.</Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() =>
            router.push(buildLoginHref(buildOrderTrackingHref(orderId)))
          }
        >
          <Text style={styles.primaryButtonText}>Sign in</Text>
        </Pressable>
      </View>
    );
  }

  if (trackingQuery.isLoading && !trackingQuery.data) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#0a7ea4" />
        <Text style={styles.muted}>Loading tracking…</Text>
      </View>
    );
  }

  if (trackingQuery.isError && !trackingQuery.data) {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Tracking unavailable</Text>
        <Text style={styles.body}>
          {getOrderErrorMessage(trackingQuery.error)}
        </Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() => void trackingQuery.refetch()}
        >
          <Text style={styles.primaryButtonText}>Retry</Text>
        </Pressable>
      </View>
    );
  }

  const tracking = trackingQuery.data;
  if (!tracking) {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>No tracking data</Text>
      </View>
    );
  }

  const events =
    tracking.unifiedTimeline.length > 0
      ? tracking.unifiedTimeline
      : tracking.timeline;

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl
          refreshing={trackingQuery.isRefetching}
          onRefresh={() => void trackingQuery.refetch()}
        />
      }
    >
      <Text style={styles.heading}>
        Tracking{tracking.orderNumber ? ` · ${tracking.orderNumber}` : ''}
      </Text>

      <Text style={styles.status}>
        {tracking.currentStatusLabel ??
          tracking.currentStatus ??
          'Status unavailable'}
      </Text>

      {tracking.shipment ? (
        <View style={styles.box}>
          <Text style={styles.section}>Shipment</Text>
          {tracking.shipment.statusLabel || tracking.shipment.status ? (
            <Text style={styles.line}>
              Status:{' '}
              {tracking.shipment.statusLabel ?? tracking.shipment.status}
            </Text>
          ) : null}
          {tracking.shipment.trackingReference ? (
            <Text style={styles.line}>
              Tracking #: {tracking.shipment.trackingReference}
            </Text>
          ) : null}
          {tracking.shipment.carrierName ? (
            <Text style={styles.line}>
              Carrier: {tracking.shipment.carrierName}
            </Text>
          ) : null}
          {tracking.shipment.transportModeLabel ? (
            <Text style={styles.line}>
              Mode: {tracking.shipment.transportModeLabel}
            </Text>
          ) : null}
        </View>
      ) : null}

      <OrderTimeline
        title="Timeline"
        progress={tracking.progress}
        events={events}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#fff' },
  content: { padding: 16, paddingBottom: 40 },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#fff',
    gap: 10,
  },
  heading: { fontSize: 22, fontWeight: '700', color: '#111' },
  title: { fontSize: 18, fontWeight: '700', color: '#222' },
  status: { marginTop: 8, fontSize: 15, fontWeight: '600', color: '#333' },
  body: { fontSize: 14, color: '#666', textAlign: 'center' },
  muted: { marginTop: 8, color: '#666' },
  box: {
    marginTop: 16,
    padding: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: '#e5e7eb',
  },
  section: { fontSize: 15, fontWeight: '700', color: '#111', marginBottom: 8 },
  line: { fontSize: 14, color: '#444', marginBottom: 4 },
  primaryButton: {
    marginTop: 12,
    backgroundColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 12,
    paddingHorizontal: 20,
    alignItems: 'center',
  },
  primaryButtonText: { color: '#fff', fontWeight: '700' },
});
