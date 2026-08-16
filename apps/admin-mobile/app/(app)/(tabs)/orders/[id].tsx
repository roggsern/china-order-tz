import type { ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { fetchOrder } from '@/src/features/orders/api/ordersApi';
import { ChannelBadge, ErrorState, LoadingState } from '@/src/shared/ui';
import { colors, radii, spacing } from '@/src/shared/theme/colors';

export default function OrderDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  const query = useQuery({
    queryKey: ['admin', 'orders', id],
    queryFn: () => fetchOrder(id),
    enabled: Boolean(id),
  });

  if (query.isLoading) return <LoadingState label="Loading order…" />;
  if (query.isError || !query.data) {
    return <ErrorState error={query.error} onRetry={() => query.refetch()} />;
  }

  const order = query.data;

  return (
    <>
      <Stack.Screen options={{ title: order.order_number }} />
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.header}>
          <Text style={styles.title}>{order.order_number}</Text>
          <ChannelBadge channel={order.commerce_channel_code} />
        </View>
        <Text style={styles.meta}>{order.status_label ?? order.status}</Text>

        <Section title="Customer">
          <Text style={styles.line}>{order.user?.name ?? '—'}</Text>
          <Text style={styles.sub}>{order.user?.email ?? '—'}</Text>
          <Text style={styles.sub}>{order.user?.phone ?? '—'}</Text>
        </Section>

        <Section title="Items">
          {order.items?.length ? (
            order.items.map((item, index) => (
              <View key={item.id ?? String(index)} style={styles.row}>
                <Text style={styles.line}>{item.product_name ?? 'Item'}</Text>
                <Text style={styles.sub}>
                  Qty {item.quantity ?? 0} · {(item.line_total ?? item.unit_price ?? 0).toLocaleString()}
                </Text>
              </View>
            ))
          ) : (
            <Text style={styles.sub}>No items loaded</Text>
          )}
        </Section>

        <Section title="Payments">
          {order.payments?.length ? (
            order.payments.map((payment, index) => (
              <View key={payment.id ?? String(index)} style={styles.row}>
                <Text style={styles.line}>{payment.status ?? 'Payment'}</Text>
                <Text style={styles.sub}>
                  {(payment.amount ?? 0).toLocaleString()} · {payment.method ?? '—'}
                </Text>
              </View>
            ))
          ) : (
            <Text style={styles.sub}>No payments</Text>
          )}
        </Section>

        {order.fulfillment ? (
          <Section title="Fulfillment">
            <Text style={styles.sub}>{JSON.stringify(order.fulfillment, null, 2)}</Text>
          </Section>
        ) : null}

        {order.refund_transactions && order.refund_transactions.length > 0 ? (
          <Section title="Refunds">
            <Text style={styles.sub}>{order.refund_transactions.length} refund record(s)</Text>
          </Section>
        ) : null}

        <Section title="Totals">
          <Text style={styles.line}>
            {order.currency ?? 'TZS'} {(order.grand_total ?? order.total ?? 0).toLocaleString()}
          </Text>
        </Section>
      </ScrollView>
    </>
  );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <View style={styles.section}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  content: { padding: spacing.lg, gap: spacing.lg, backgroundColor: colors.background },
  header: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', gap: spacing.sm },
  title: { fontSize: 20, fontWeight: '800', color: colors.navy, flex: 1 },
  meta: { color: colors.textMuted, fontSize: 13 },
  section: {
    backgroundColor: colors.surface,
    borderRadius: radii.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.border,
    gap: spacing.sm,
  },
  sectionTitle: { fontSize: 14, fontWeight: '700', color: colors.navy },
  line: { fontSize: 14, color: colors.text, fontWeight: '600' },
  sub: { fontSize: 12, color: colors.textMuted },
  row: { gap: spacing.xs, paddingVertical: spacing.xs, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: colors.border },
});
