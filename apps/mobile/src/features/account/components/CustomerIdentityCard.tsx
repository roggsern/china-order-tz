import { StyleSheet, Text, View } from 'react-native';
import { BRAND_NAME } from '@/src/shared/branding';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { User } from '@/src/shared/types/user';

type Props = {
  user: User | null;
};

function initialsFromUser(user: User | null): string {
  const name = user?.name?.trim();
  if (name) {
    const parts = name.split(/\s+/).filter(Boolean);
    const letters = parts
      .slice(0, 2)
      .map((part) => part[0]?.toUpperCase() ?? '')
      .join('');
    if (letters) return letters;
  }
  const email = user?.email?.trim();
  if (email) return email[0]?.toUpperCase() ?? '?';
  return '?';
}

/** Branded customer identity card — display only. */
export function CustomerIdentityCard({ user }: Props) {
  const initials = initialsFromUser(user);
  const verified = Boolean(user?.email_verified_at);

  return (
    <Card elevated style={styles.card}>
      <View style={styles.row}>
        <View style={styles.avatar} accessibilityLabel="Profile avatar placeholder">
          <Text style={styles.avatarText}>{initials}</Text>
        </View>
        <View style={styles.copy}>
          <Text style={styles.brand}>{BRAND_NAME}</Text>
          <Text style={styles.name} numberOfLines={1}>
            {user?.name?.trim() || 'Customer'}
          </Text>
          <Text style={styles.email} numberOfLines={1}>
            {user?.email ?? 'Not signed in'}
          </Text>
          {user?.phone ? (
            <Text style={styles.phone} numberOfLines={1}>
              {user.phone}
            </Text>
          ) : null}
          <View style={styles.badges}>
            <Badge
              label={verified ? 'Verified email' : 'Account'}
              tone={verified ? 'success' : 'brand'}
            />
          </View>
        </View>
      </View>
    </Card>
  );
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: colors.surfaceCream,
    borderColor: colors.primary,
    marginBottom: spacing.lg,
  },
  row: {
    flexDirection: 'row',
    gap: spacing.md,
    alignItems: 'center',
  },
  avatar: {
    width: 64,
    height: 64,
    borderRadius: radius.full,
    backgroundColor: colors.primaryMuted,
    borderWidth: 1,
    borderColor: colors.primary,
    alignItems: 'center',
    justifyContent: 'center',
  },
  avatarText: {
    ...typography.title,
    color: colors.primaryPressed,
  },
  copy: { flex: 1 },
  brand: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.7,
    marginBottom: spacing.xxs,
  },
  name: {
    ...typography.title,
    fontSize: 18,
  },
  email: {
    ...typography.body,
    marginTop: spacing.xxs,
  },
  phone: {
    ...typography.caption,
    marginTop: spacing.xxs,
  },
  badges: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginTop: spacing.sm,
  },
});
