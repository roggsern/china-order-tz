import { useState } from 'react';
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
import { formatCustomerDateTime } from '@/src/shared/utils/formatCustomerDateTime';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { colors, spacing, typography } from '@/src/shared/theme';
import { shouldOfferReturnRequest } from '@/src/features/returns/utils/returnEligibility';
import { buildOrderReturnHref } from '@/src/features/returns/utils/returnRoutes';
import { CancelOrderButton } from '../components/CancelOrderButton';
import { ContinuePaymentButton } from '../components/ContinuePaymentButton';
import { OrderDeliveryOptionPanel } from '../components/OrderDeliveryOptionPanel';
import { OrderFulfillmentBlock } from '../components/OrderFulfillmentBlock';
import { OrderReceivingChoicePanel } from '../components/OrderReceivingChoicePanel';
import { OrderItemRow } from '../components/OrderItemRow';
import { OrderPaymentBlock } from '../components/OrderPaymentBlock';
import { OrderSummaryBlock } from '../components/OrderSummaryBlock';
import { OrderTimeline } from '../components/OrderTimeline';
import { useOrderDetail } from '../hooks/useOrders';
import { isOrderPayableFromServer } from '../utils/isOrderPayable';
import { hasOrderTrackingEntry } from '../utils/hasOrderTrackingEntry';
import { shouldOfferCancel } from '../utils/mapOrders';
import {
  buildOrderLifecyclePresentation,
  orderDisplayTone,
  resolveProgressForDisplay,
} from '../utils/orderLifecycleDisplay';
import { getOrderErrorMessage } from '../utils/orderErrorMessage';
import {
  buildOrderDetailHref,
  buildOrderTrackingHref,
} from '../utils/orderRoutes';

type Props = {
  orderId: string;
};

export function OrderDetailScreen({ orderId }: Props) {
  const authStatus = useAuthStore((s) => s.status);
  const detailQuery = useOrderDetail(orderId);
  const [cancelHidden, setCancelHidden] = useState(false);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  if (authStatus !== 'authenticated') {
    return (
      <EmptyState
        title="Order"
        message="Please sign in to view this order."
        actionLabel="Sign in"
        onActionPress={() =>
          router.push(buildLoginHref(buildOrderDetailHref(orderId)))
        }
        style={styles.fill}
      />
    );
  }

  if (detailQuery.isLoading && !detailQuery.data) {
    return <ScreenLoadingState label="Loading order…" />;
  }

  if (detailQuery.isError && !detailQuery.data) {
    return (
      <EmptyState
        title="Order unavailable"
        message={getOrderErrorMessage(detailQuery.error)}
        actionLabel="Retry"
        onActionPress={() => void detailQuery.refetch()}
        style={styles.fill}
      />
    );
  }

  const order = detailQuery.data;
  if (!order) {
    return (
      <EmptyState
        title="Order not found"
        message="This order could not be loaded."
        style={styles.fill}
      />
    );
  }

  const offerCancel =
    !cancelHidden &&
    shouldOfferCancel({
      status: order.status,
      canCancel: order.canCancel,
      progress: order.progress,
    });
  const offerContinuePayment = isOrderPayableFromServer({
    status: order.status,
    canPay: order.canPay,
    paymentStatus: order.payment?.paymentStatus,
  });
  const lifecycle = buildOrderLifecyclePresentation({
    status: order.status,
    statusLabel: order.statusLabel,
    paymentStatus: order.payment?.paymentStatus,
    paymentMethod: order.payment?.paymentMethod,
    paymentProvider: order.payment?.provider,
    transactionStatus: order.activePaymentTransaction?.status,
    progress: order.progress,
    shipment: order.shipment,
    receivingChoice: order.receivingChoice,
  });
  const displayProgress = resolveProgressForDisplay(order.status, order.progress);
  const offerReturn = shouldOfferReturnRequest(order.status);

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl
          refreshing={detailQuery.isRefetching}
          onRefresh={() => void detailQuery.refetch()}
          tintColor={colors.primary}
          colors={[colors.primary]}
        />
      }
    >
      <Text style={styles.eyebrow}>Order detail</Text>
      <Text style={styles.heading}>{order.orderNumber ?? order.id}</Text>
      <View style={styles.badgeRow}>
        {order.journeyLabel ? (
          <Badge label={order.journeyLabel} tone="brand" />
        ) : null}
        <Badge
          label={lifecycle.headline.label}
          tone={orderDisplayTone(lifecycle.headline.key)}
        />
      </View>
      {order.createdAt ? (
        <Text style={styles.meta}>{formatCustomerDateTime(order.createdAt)}</Text>
      ) : null}

      {actionMessage ? (
        <Text style={styles.notice}>{actionMessage}</Text>
      ) : null}

      <Text style={styles.section}>Items</Text>
      {order.items.map((item) => (
        <OrderItemRow
          key={item.id}
          item={item}
          currency={order.currency}
        />
      ))}

      <OrderSummaryBlock summary={order.summary} currency={order.currency} />

      {order.payment ? (
        <OrderPaymentBlock
          payment={order.payment}
          display={lifecycle.payment}
          orderStatus={order.status}
        />
      ) : null}

      <ContinuePaymentButton
        orderId={orderId}
        enabled={offerContinuePayment}
      />

      <OrderReceivingChoicePanel
        orderId={orderId}
        orderStatus={order.status}
        progress={order.progress}
        receivingChoice={order.receivingChoice}
        onUpdated={() => void detailQuery.refetch()}
      />

      <OrderDeliveryOptionPanel
        orderId={orderId}
        orderStatus={order.status}
        paymentStatus={order.payment?.paymentStatus ?? null}
      />

      <OrderFulfillmentBlock
        fulfillment={lifecycle.fulfillment}
        receiving={lifecycle.receiving}
      />

      <OrderTimeline progress={displayProgress} />

      {lifecycle.fulfillment.showProgression &&
      order.shipment &&
      (order.shipment.status ||
        order.shipment.statusLabel ||
        order.shipment.trackingReference ||
        order.shipment.carrierName) ? (
        <Card elevated={false} style={styles.shipmentBox}>
          <Text style={styles.section}>Shipment</Text>
          {order.shipment.statusLabel || order.shipment.status ? (
            <Text style={styles.meta}>
              {order.shipment.statusLabel ?? order.shipment.status}
            </Text>
          ) : null}
          {order.shipment.trackingReference ? (
            <Text style={styles.meta}>
              Tracking #: {order.shipment.trackingReference}
            </Text>
          ) : null}
          {order.shipment.carrierName ? (
            <Text style={styles.meta}>Carrier: {order.shipment.carrierName}</Text>
          ) : null}
        </Card>
      ) : null}

      {hasOrderTrackingEntry(order) ? (
        <SecondaryButton
          label="View tracking"
          onPress={() => router.push(buildOrderTrackingHref(orderId))}
          style={styles.trackingButton}
        />
      ) : null}

      {offerReturn ? (
        <SecondaryButton
          label="Request return"
          onPress={() => router.push(buildOrderReturnHref(orderId) as never)}
          style={styles.trackingButton}
        />
      ) : null}

      <CancelOrderButton
        orderId={orderId}
        enabled={offerCancel}
        onCancelled={() => {
          setActionMessage('Your order was cancelled.');
          void detailQuery.refetch();
        }}
        onRejected={(message) => {
          setCancelHidden(true);
          setActionMessage(message);
          void detailQuery.refetch();
        }}
      />
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
  badgeRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginTop: spacing.sm,
  },
  meta: { marginTop: spacing.sm, ...typography.caption },
  notice: {
    marginTop: spacing.md,
    ...typography.bodyStrong,
    color: colors.primaryPressed,
  },
  section: {
    marginTop: spacing.xl,
    marginBottom: spacing.sm,
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
  },
  shipmentBox: {
    marginTop: spacing.md,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  trackingButton: {
    marginTop: spacing.lg,
    alignSelf: 'stretch',
  },
});
