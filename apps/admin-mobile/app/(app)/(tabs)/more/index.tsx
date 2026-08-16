import { Ionicons } from '@expo/vector-icons';
import { router, Stack } from 'expo-router';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';

import { useAdminAuthStore } from '@/src/core/auth';
import { canAccessMoreLink } from '@/src/features/nav/navAuthorization';
import { colors, radii, spacing } from '@/src/shared/theme/colors';

type LinkItem = {
  key: 'customers' | 'lowStock' | 'account';
  title: string;
  subtitle: string;
  href: string;
  icon: keyof typeof Ionicons.glyphMap;
};

const LINKS: LinkItem[] = [
  {
    key: 'customers',
    title: 'Customers',
    subtitle: 'Search and view customer profiles',
    href: '/(app)/(tabs)/more/customers',
    icon: 'people-outline',
  },
  {
    key: 'lowStock',
    title: 'Low stock',
    subtitle: 'Inventory below reorder levels',
    href: '/(app)/(tabs)/more/low-stock',
    icon: 'cube-outline',
  },
  {
    key: 'account',
    title: 'Account',
    subtitle: 'Profile and sign out',
    href: '/(app)/(tabs)/more/account',
    icon: 'person-circle-outline',
  },
];

export default function MoreScreen() {
  const admin = useAdminAuthStore((s) => s.admin);

  return (
    <>
      <Stack.Screen options={{ title: 'More' }} />
      <ScrollView contentContainerStyle={styles.content}>
        {LINKS.filter((link) => canAccessMoreLink(admin, link.key)).map((link) => (
          <Pressable key={link.key} style={styles.card} onPress={() => router.push(link.href)}>
            <Ionicons name={link.icon} size={22} color={colors.gold} />
            <View style={styles.cardText}>
              <Text style={styles.title}>{link.title}</Text>
              <Text style={styles.subtitle}>{link.subtitle}</Text>
            </View>
            <Ionicons name="chevron-forward" size={18} color={colors.textMuted} />
          </Pressable>
        ))}
      </ScrollView>
    </>
  );
}

const styles = StyleSheet.create({
  content: { padding: spacing.lg, gap: spacing.sm, backgroundColor: colors.background },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    backgroundColor: colors.surface,
    borderRadius: radii.md,
    padding: spacing.md,
    borderWidth: 1,
    borderColor: colors.border,
  },
  cardText: { flex: 1 },
  title: { fontSize: 15, fontWeight: '700', color: colors.navy },
  subtitle: { marginTop: spacing.xs, fontSize: 12, color: colors.textMuted },
});
