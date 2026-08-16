import { router, Stack } from 'expo-router';
import { StyleSheet, Text, View } from 'react-native';

import { logout, useAdminAuthStore } from '@/src/core/auth';
import { PrimaryButton } from '@/src/shared/ui';
import { colors, radii, spacing } from '@/src/shared/theme/colors';

export default function AccountScreen() {
  const admin = useAdminAuthStore((s) => s.admin);

  async function handleLogout() {
    await logout();
    router.replace('/(auth)/login');
  }

  return (
    <>
      <Stack.Screen options={{ title: 'Account' }} />
      <View style={styles.container}>
        <View style={styles.card}>
          <Text style={styles.label}>Signed in as</Text>
          <Text style={styles.name}>{admin?.name ?? '—'}</Text>
          <Text style={styles.meta}>{admin?.email ?? '—'}</Text>
          <Text style={styles.meta}>
            Role: {admin?.role?.name ?? admin?.role?.slug ?? (admin?.is_super_admin ? 'Super admin' : '—')}
          </Text>
        </View>

        <PrimaryButton label="Sign out" onPress={handleLogout} />
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: spacing.lg, gap: spacing.lg, backgroundColor: colors.background },
  card: {
    backgroundColor: colors.surface,
    borderRadius: radii.md,
    padding: spacing.lg,
    borderWidth: 1,
    borderColor: colors.border,
    gap: spacing.xs,
  },
  label: { fontSize: 12, color: colors.textMuted, textTransform: 'uppercase' },
  name: { fontSize: 20, fontWeight: '800', color: colors.navy },
  meta: { fontSize: 14, color: colors.textMuted },
});
