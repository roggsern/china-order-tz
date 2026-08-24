import { RefreshControl, ScrollView, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { buildOrderDetailHref } from '@/src/features/orders/utils/orderRoutes';
import { formatCustomerDateTime } from '@/src/shared/utils/formatCustomerDateTime';
import { formatCustomerMoney } from '@/src/shared/utils/formatCustomerMoney';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { colors, spacing, typography } from '@/src/shared/theme';
import { useCustomerReturnDetail } from '../hooks/useReturns';
import { getReturnErrorMessage } from '../utils/returnErrorMessage';
import {
  resolveRefundDisplayStatus,
  resolveReturnDisplayStatus,
  returnDisplayTone,
} from '../utils/returnStatusDisplay';
import { buildReturnDetailHref } from '../utils/returnRoutes';

type Props = {
  returnId: string;
};

export function ReturnDetailScreen({ returnId }: Props) {
  const authStatus = useAuthStore((s) => s.status);
  const query = useCustomerReturnDetail(returnId);

  if (authStatus !== 'authenticated') {
    return (
      <EmptyState
        title="Return"
        message="Please sign in to view this return request."
        actionLabel="Sign in"
        onActionPress={() =>
          router.push(buildLoginHref(buildReturnDetailHref(returnId)))
        }
        style={styles.fill}
      />
    );
  }

  if (query.isLoading && !query.data) {
    return <ScreenLoadingState label="Loading return…" />;
  }

  if (query.isError && !query.data) {
    return (
      <EmptyState
        title="Return unavailable"
        message={getReturnErrorMessage(query.error)}
        actionLabel="Retry"
        onActionPress={() => void query.refetch()}
        style={styles.fill}
      />
    );
  }

  const row = query.data;
  if (!row) {
    return (
      <EmptyState
        title="Return not found"
        message="This return request could not be loaded."
        style={styles.fill}
      />
    );
  }

  const status = resolveReturnDisplayStatus(row.status);

  return (
    <ScrollView
      style={styles.screen}
      contentContainerStyle={styles.content}
      refreshControl={
        <RefreshControl
          refreshing={query.isRefetching}
          onRefresh={() => void query.refetch()}
          tintColor={colors.primary}
          colors={[colors.primary]}
        />
      }
    >
      <Text style={styles.eyebrow}>Return request</Text>
      <Text style={styles.heading}>{row.orderNumber ?? row.id}</Text>
      <View style={styles.badges}>
        <Badge label={status.label} tone={returnDisplayTone(status.key)} />
      </View>
      {row.createdAt ? (
        <Text style={styles.meta}>{formatCustomerDateTime(row.createdAt)}</Text>
      ) : null}

      {row.reason ? (
        <Card elevated={false} style={styles.block}>
          <Text style={styles.section}>Reason</Text>
          <Text style={styles.body}>{row.reason}</Text>
          {row.description ? <Text style={styles.meta}>{row.description}</Text> : null}
          {row.customerNotes ? (
            <Text style={styles.meta}>Notes: {row.customerNotes}</Text>
          ) : null}
        </Card>
      ) : null}

      <Text style={styles.section}>Items</Text>
      {row.items.map((item) => (
        <Card key={item.id} elevated={false} style={styles.block}>
          <Text style={styles.body}>{item.productName ?? item.orderItemId}</Text>
          <Text style={styles.meta}>Qty {item.quantity}</Text>
          {item.reason ? <Text style={styles.meta}>{item.reason}</Text> : null}
        </Card>
      ))}

      <Card elevated={false} style={styles.block}>
        <Text style={styles.section}>Refund</Text>
        {row.refunds.length === 0 ? (
          <Text style={styles.meta}>
            No refund has been issued yet.
          </Text>
        ) : (
          row.refunds.map((refund) => {
            const refundStatus = resolveRefundDisplayStatus(refund.status);
            return (
              <View key={refund.id} style={styles.refundRow}>
                <Badge
                  label={refund.statusLabel ?? refundStatus.label}
                  tone={returnDisplayTone(refundStatus.key)}
                />
                {refund.amount != null ? (
                  <Text style={styles.meta}>
                    {formatCustomerMoney(refund.amount, refund.currency ?? 'TZS')}
                  </Text>
                ) : null}
              </View>
            );
          })
        )}
      </Card>

      {row.orderId ? (
        <SecondaryButton
          label="View order"
          onPress={() => router.push(buildOrderDetailHref(row.orderId!) as never)}
          style={styles.orderButton}
        />
      ) : null}
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
  },
  heading: { ...typography.heading, marginTop: spacing.xs },
  badges: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginTop: spacing.sm,
  },
  meta: { marginTop: spacing.xs, ...typography.caption },
  body: { ...typography.body, color: colors.text },
  section: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    marginBottom: spacing.sm,
  },
  block: {
    marginTop: spacing.lg,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  refundRow: { marginBottom: spacing.sm },
  orderButton: { marginTop: spacing.xl, alignSelf: 'stretch' },
});
