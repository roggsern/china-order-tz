import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { formatCustomerDateTime } from '@/src/shared/utils/formatCustomerDateTime';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { colors, spacing, typography } from '@/src/shared/theme';
import { useCustomerReturnsList } from '../hooks/useReturns';
import { getReturnErrorMessage } from '../utils/returnErrorMessage';
import {
  resolveRefundDisplayStatus,
  resolveReturnDisplayStatus,
  returnDisplayTone,
} from '../utils/returnStatusDisplay';
import { buildReturnDetailHref, buildReturnsListHref } from '../utils/returnRoutes';

export function ReturnsListScreen() {
  const authStatus = useAuthStore((s) => s.status);
  const query = useCustomerReturnsList();

  if (authStatus !== 'authenticated') {
    return (
      <EmptyState
        title="Returns"
        message="Please sign in to view your return requests."
        actionLabel="Sign in"
        onActionPress={() => router.push(buildLoginHref(buildReturnsListHref()))}
        style={styles.fill}
      />
    );
  }

  if (query.isLoading && !query.data) {
    return <ScreenLoadingState label="Loading returns…" />;
  }

  if (query.isError && !query.data) {
    return (
      <EmptyState
        title="Returns unavailable"
        message={getReturnErrorMessage(query.error)}
        actionLabel="Retry"
        onActionPress={() => void query.refetch()}
        style={styles.fill}
      />
    );
  }

  const rows = query.data?.returns ?? [];

  if (rows.length === 0) {
    return (
      <EmptyState
        title="No returns yet"
        message="Return requests you submit will appear here with live updates."
        style={styles.fill}
      />
    );
  }

  return (
    <FlatList
      style={styles.screen}
      contentContainerStyle={styles.content}
      data={rows}
      keyExtractor={(item) => item.id}
      refreshControl={
        <RefreshControl
          refreshing={query.isRefetching}
          onRefresh={() => void query.refetch()}
          tintColor={colors.primary}
          colors={[colors.primary]}
        />
      }
      renderItem={({ item }) => {
        const status = resolveReturnDisplayStatus(item.status);
        const latestRefund = item.refunds[0] ?? null;
        const refund = latestRefund
          ? resolveRefundDisplayStatus(latestRefund.status)
          : null;
        return (
          <Pressable
            accessibilityRole="button"
            onPress={() => router.push(buildReturnDetailHref(item.id) as never)}
          >
            <Card elevated style={styles.card}>
              <Text style={styles.order}>
                {item.orderNumber ?? item.orderId ?? 'Return request'}
              </Text>
              <View style={styles.badges}>
                <Badge label={status.label} tone={returnDisplayTone(status.key)} />
                {refund && refund.key !== 'none' ? (
                  <Badge
                    label={refund.label}
                    tone={returnDisplayTone(refund.key)}
                  />
                ) : null}
              </View>
              {item.reason ? <Text style={styles.meta}>{item.reason}</Text> : null}
              {item.createdAt ? (
                <Text style={styles.meta}>
                  {formatCustomerDateTime(item.createdAt)}
                </Text>
              ) : null}
            </Card>
          </Pressable>
        );
      }}
    />
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
  card: { marginBottom: spacing.md },
  order: { ...typography.bodyStrong, color: colors.text },
  badges: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginTop: spacing.sm,
  },
  meta: { marginTop: spacing.xs, ...typography.caption },
});
