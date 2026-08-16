import type { ReactNode } from 'react';
import { useQuery } from '@tanstack/react-query';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

import { useAdminAuthStore } from '@/src/core/auth';
import { fetchAlerts, fetchDashboard } from '@/src/features/dashboard/api/dashboardApi';
import { canViewAlerts } from '@/src/features/nav/navAuthorization';
import { EmptyState, ErrorState, LoadingState } from '@/src/shared/ui';
import { colors, radii, spacing } from '@/src/shared/theme/colors';

function MetricCard({ label, value }: { label: string; value?: number | string }) {
  return (
    <View style={styles.metricCard}>
      <Text style={styles.metricValue}>{value ?? '—'}</Text>
      <Text style={styles.metricLabel}>{label}</Text>
    </View>
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

export default function DashboardScreen() {
  const admin = useAdminAuthStore((s) => s.admin);
  const showAlerts = canViewAlerts(admin);

  const dashboardQuery = useQuery({
    queryKey: ['admin', 'dashboard'],
    queryFn: fetchDashboard,
  });

  const alertsQuery = useQuery({
    queryKey: ['admin', 'alerts'],
    queryFn: fetchAlerts,
    enabled: showAlerts,
  });

  if (dashboardQuery.isLoading) return <LoadingState label="Loading dashboard…" />;
  if (dashboardQuery.isError) {
    return <ErrorState error={dashboardQuery.error} onRetry={() => dashboardQuery.refetch()} />;
  }

  const data = dashboardQuery.data;
  const overview = data?.overview;

  return (
    <SafeAreaView style={styles.safe} edges={['bottom']}>
      <ScrollView contentContainerStyle={styles.content}>
        <Section title="Overview">
          <View style={styles.metricGrid}>
            <MetricCard label="Orders today" value={overview?.orders_today} />
            <MetricCard label="Revenue today" value={overview?.revenue_today} />
            <MetricCard label="Paid today" value={overview?.paid_orders_today} />
            <MetricCard label="Pending actions" value={overview?.pending_actions} />
            <MetricCard label="Customers" value={overview?.customers_total} />
            <MetricCard label="New customers" value={overview?.new_customers} />
          </View>
        </Section>

        {data?.attention_items && data.attention_items.length > 0 ? (
          <Section title="Needs attention">
            {data.attention_items.map((item, index) => (
              <View key={item.key ?? String(index)} style={styles.rowCard}>
                <Text style={styles.rowTitle}>{item.label ?? item.key}</Text>
                <Text style={styles.rowMeta}>{item.count ?? 0}</Text>
              </View>
            ))}
          </Section>
        ) : null}

        <Section title="Operations">
          <View style={styles.rowCard}>
            <Text style={styles.rowTitle}>Fulfillment queue</Text>
            <Text style={styles.rowMeta}>
              Total {data?.operations?.fulfillment_queue?.total ?? 0} · China{' '}
              {data?.operations?.fulfillment_queue?.china ?? 0} · Local{' '}
              {data?.operations?.fulfillment_queue?.local ?? 0}
            </Text>
            <Text style={styles.rowSub}>Open returns: {data?.operations?.open_returns ?? 0}</Text>
          </View>
        </Section>

        <Section title="China pipeline">
          {data?.china_pipeline && Object.keys(data.china_pipeline).length > 0 ? (
            Object.entries(data.china_pipeline).map(([key, value]) => (
              <View key={key} style={styles.rowCard}>
                <Text style={styles.rowTitle}>{key.replace(/_/g, ' ')}</Text>
                <Text style={styles.rowMeta}>{String(value ?? 0)}</Text>
              </View>
            ))
          ) : (
            <EmptyState title="No China pipeline data" />
          )}
        </Section>

        <Section title="TZ local">
          {data?.tz_local && Object.keys(data.tz_local).length > 0 ? (
            Object.entries(data.tz_local).map(([key, value]) => (
              <View key={key} style={styles.rowCard}>
                <Text style={styles.rowTitle}>{key.replace(/_/g, ' ')}</Text>
                <Text style={styles.rowMeta}>{String(value ?? 0)}</Text>
              </View>
            ))
          ) : (
            <EmptyState title="No TZ local data" />
          )}
        </Section>

        {showAlerts ? (
          <Section title="Alerts">
            {alertsQuery.isLoading ? (
              <Text style={styles.rowSub}>Loading alerts…</Text>
            ) : alertsQuery.isError ? (
              <Text style={styles.rowSub}>Unable to load alerts.</Text>
            ) : alertsQuery.data?.alerts && alertsQuery.data.alerts.length > 0 ? (
              alertsQuery.data.alerts.map((alert, index) => (
                <View key={`${alert.title}-${index}`} style={styles.rowCard}>
                  <Text style={styles.rowTitle}>{alert.title}</Text>
                  <Text style={styles.rowSub}>{alert.message}</Text>
                </View>
              ))
            ) : (
              <EmptyState title="No active alerts" />
            )}
          </Section>
        ) : null}
      </ScrollView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safe: { flex: 1, backgroundColor: colors.background },
  content: { padding: spacing.lg, gap: spacing.lg, paddingBottom: spacing.xl },
  section: { gap: spacing.sm },
  sectionTitle: { fontSize: 16, fontWeight: '700', color: colors.navy },
  metricGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  metricCard: {
    width: '48%',
    backgroundColor: colors.surface,
    borderRadius: radii.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.border,
  },
  metricValue: { fontSize: 20, fontWeight: '800', color: colors.navy },
  metricLabel: { marginTop: spacing.xs, fontSize: 12, color: colors.textMuted },
  rowCard: {
    backgroundColor: colors.surface,
    borderRadius: radii.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.border,
    marginBottom: spacing.sm,
  },
  rowTitle: { fontSize: 14, fontWeight: '600', color: colors.text },
  rowMeta: { marginTop: spacing.xs, fontSize: 13, color: colors.navy, fontWeight: '700' },
  rowSub: { marginTop: spacing.xs, fontSize: 12, color: colors.textMuted },
});
