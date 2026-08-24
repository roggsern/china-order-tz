import {
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { Card } from '@/src/shared/ui/Card';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { colors, spacing, typography } from '@/src/shared/theme';
import { OrderThumbnail } from '../components/OrderThumbnail';
import { OrderTimeline } from '../components/OrderTimeline';
import { useOrderDetail, useOrderTracking } from '../hooks/useOrders';
import { collectOrderItemImageUrls } from '../utils/orderCardPresentation';
import { getOrderErrorMessage } from '../utils/orderErrorMessage';
import { buildOrderTrackingHref } from '../utils/orderRoutes';
import {
  buildOrderLifecyclePresentation,
  resolveProgressForDisplay,
  resolveTrackingHeroLabel,
} from '../utils/orderLifecycleDisplay';

type Props = {
  orderId: string;
};

export function OrderTrackingScreen({ orderId }: Props) {
  const authStatus = useAuthStore((s) => s.status);
  const trackingQuery = useOrderTracking(orderId);
  const detailQuery = useOrderDetail(orderId);

  if (authStatus !== 'authenticated') {
    return (
      <EmptyState
        title="Tracking"
        message="Please sign in to view tracking."
        actionLabel="Sign in"
        onActionPress={() =>
          router.push(buildLoginHref(buildOrderTrackingHref(orderId)))
        }
        style={styles.fill}
      />
    );
  }

  if (trackingQuery.isLoading && !trackingQuery.data) {
    return <ScreenLoadingState label="Loading tracking…" />;
  }

  if (trackingQuery.isError && !trackingQuery.data) {
    return (
      <EmptyState
        title="Tracking unavailable"
        message={getOrderErrorMessage(trackingQuery.error)}
        actionLabel="Retry"
        onActionPress={() => void trackingQuery.refetch()}
        style={styles.fill}
      />
    );
  }

  const tracking = trackingQuery.data;
  if (!tracking) {
    return (
      <EmptyState
        title="No tracking data"
        message="Tracking details are not available for this order yet."
        style={styles.fill}
      />
    );
  }

  const detail = detailQuery.data;
  const lifecycle = buildOrderLifecyclePresentation({
    status: detail?.status ?? null,
    statusLabel: detail?.statusLabel,
    paymentStatus: detail?.payment?.paymentStatus,
    paymentMethod: detail?.payment?.paymentMethod,
    paymentProvider: detail?.payment?.provider,
    progress: tracking.progress ?? detail?.progress,
    shipment: tracking.shipment
      ? {
          status: tracking.shipment.status,
          statusLabel: tracking.shipment.statusLabel,
        }
      : detail?.shipment,
    receivingChoice: detail?.receivingChoice,
  });
  const displayProgress = resolveProgressForDisplay(
    detail?.status ?? null,
    tracking.progress ?? detail?.progress ?? null,
  );
  const events = lifecycle.fulfillment.showProgression
    ? tracking.unifiedTimeline.length > 0
      ? tracking.unifiedTimeline
      : tracking.timeline
    : [];

  const hasTimeline = events.length > 0 || Boolean(displayProgress?.steps?.length);
  const itemImages = collectOrderItemImageUrls(detail?.items);
  const detailItems = detail?.items ?? [];
  const heroStatus = resolveTrackingHeroLabel({
    orderStatus: detail?.status ?? null,
    trackingCurrentLabel: tracking.currentStatusLabel,
    trackingCurrentStatus: tracking.currentStatus,
    progress: tracking.progress ?? detail?.progress,
    receivingChoice: detail?.receivingChoice,
  });

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl
          refreshing={
            trackingQuery.isRefetching || detailQuery.isRefetching
          }
          onRefresh={() => {
            void trackingQuery.refetch();
            void detailQuery.refetch();
          }}
          tintColor={colors.primary}
          colors={[colors.primary]}
        />
      }
    >
      <Text style={styles.eyebrow}>Tracking</Text>
      <Text style={styles.heading}>
        {tracking.orderNumber
          ? `Order ${tracking.orderNumber}`
          : 'Shipment progress'}
      </Text>

      <Text style={styles.status}>{heroStatus}</Text>

      {detailItems.length > 0 ? (
        <Card elevated={false} style={styles.box}>
          <Text style={styles.section}>Items in this order</Text>
          <View style={styles.itemRow}>
            {detailItems.slice(0, 4).map((item) => (
              <View key={item.id} style={styles.itemChip}>
                <OrderThumbnail imageUrl={item.imageUrl} size={56} />
                <Text style={styles.itemName} numberOfLines={2}>
                  {item.productName}
                </Text>
              </View>
            ))}
            {detailItems.length > 4 ? (
              <Text style={styles.moreItems}>
                +{detailItems.length - 4} more
              </Text>
            ) : null}
          </View>
          {itemImages.length === 0 ? (
            <Text style={styles.line}>
              Product images are not available for these line items yet.
            </Text>
          ) : null}
        </Card>
      ) : null}

      {lifecycle.fulfillment.showProgression && tracking.shipment ? (
        <Card elevated={false} style={styles.box}>
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
        </Card>
      ) : (
        <Card elevated={false} style={styles.box}>
          <Text style={styles.section}>Shipment</Text>
          <Text style={styles.line}>
            {lifecycle.fulfillment.showProgression
              ? 'No shipment tracking has been published for this order yet.'
              : lifecycle.order.key === 'cancelled'
                ? 'Shipment is not active for this cancelled order.'
                : 'Shipment has not started.'}
          </Text>
        </Card>
      )}

      {hasTimeline ? (
        <OrderTimeline
          title="Timeline"
          progress={displayProgress}
          events={events}
        />
      ) : (
        <Card elevated={false} style={styles.box}>
          <Text style={styles.section}>Timeline</Text>
          <Text style={styles.line}>
            Tracking updates will appear here as your order moves.
          </Text>
        </Card>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: colors.background },
  content: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.huge,
  },
  fill: { flex: 1, backgroundColor: colors.background },
  eyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: spacing.xs,
  },
  heading: { ...typography.heading },
  status: {
    marginTop: spacing.sm,
    ...typography.bodyStrong,
  },
  box: {
    marginTop: spacing.lg,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  section: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    marginBottom: spacing.sm,
  },
  line: { ...typography.body, marginBottom: spacing.xs },
  itemRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.sm,
    marginBottom: spacing.sm,
  },
  itemChip: {
    width: 72,
    gap: spacing.xxs,
  },
  itemName: {
    ...typography.caption,
    fontSize: 11,
    color: colors.textSecondary,
  },
  moreItems: {
    ...typography.caption,
    alignSelf: 'center',
    color: colors.textMuted,
  },
});
