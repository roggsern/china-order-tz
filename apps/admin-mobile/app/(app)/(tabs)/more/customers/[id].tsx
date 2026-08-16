import { useQuery } from '@tanstack/react-query';
import { Stack, useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { fetchCustomer } from '@/src/features/customers/api/customersApi';
import { ErrorState, LoadingState } from '@/src/shared/ui';
import { colors, radii, spacing } from '@/src/shared/theme/colors';

export default function CustomerDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();

  const query = useQuery({
    queryKey: ['admin', 'customers', id],
    queryFn: () => fetchCustomer(id),
    enabled: Boolean(id),
  });

  if (query.isLoading) return <LoadingState label="Loading customer…" />;
  if (query.isError || !query.data) {
    return <ErrorState error={query.error} onRetry={() => query.refetch()} />;
  }

  const customer = query.data;

  return (
    <>
      <Stack.Screen options={{ title: customer.name ?? 'Customer' }} />
      <ScrollView contentContainerStyle={styles.content}>
        <View style={styles.section}>
          <Text style={styles.label}>Name</Text>
          <Text style={styles.value}>{customer.name ?? '—'}</Text>
          <Text style={styles.label}>Email</Text>
          <Text style={styles.value}>{customer.email ?? '—'}</Text>
          <Text style={styles.label}>Phone</Text>
          <Text style={styles.value}>{customer.phone ?? '—'}</Text>
          <Text style={styles.label}>Code</Text>
          <Text style={styles.value}>{customer.customer_code ?? customer.id}</Text>
          <Text style={styles.label}>Lifecycle</Text>
          <Text style={styles.value}>{customer.lifecycle_status ?? '—'}</Text>
        </View>

        {customer.metrics ? (
          <View style={styles.section}>
            <Text style={styles.sectionTitle}>Metrics</Text>
            <Text style={styles.value}>Orders: {customer.metrics.total_orders ?? 0}</Text>
            <Text style={styles.value}>
              Spend: {(customer.metrics.total_spend ?? 0).toLocaleString()}
            </Text>
            <Text style={styles.sub}>Last order: {customer.metrics.last_order_at ?? '—'}</Text>
          </View>
        ) : null}
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  content: { padding: spacing.lg, gap: spacing.lg, backgroundColor: colors.background },
  section: {
    backgroundColor: colors.surface,
    borderRadius: radii.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.border,
    gap: spacing.xs,
  },
  sectionTitle: { fontSize: 14, fontWeight: '700', color: colors.navy, marginBottom: spacing.sm },
  label: { fontSize: 11, color: colors.textMuted, marginTop: spacing.sm, textTransform: 'uppercase' },
  value: { fontSize: 14, color: colors.text, fontWeight: '600' },
  sub: { fontSize: 12, color: colors.textMuted },
});
