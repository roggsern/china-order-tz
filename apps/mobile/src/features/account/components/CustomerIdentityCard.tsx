import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';
import { BRAND_NAME } from '@/src/shared/branding';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import type { User } from '@/src/shared/types/user';

type Props = {
  user: User | null;
  /** Resend verification email when unverified. */
  onResendVerification?: () => void;
  resendBusy?: boolean;
  resendMessage?: string | null;
  onRefreshVerification?: () => void;
  refreshBusy?: boolean;
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

/** Branded customer identity card with email verification status/actions. */
export function CustomerIdentityCard({
  user,
  onResendVerification,
  resendBusy = false,
  resendMessage = null,
  onRefreshVerification,
  refreshBusy = false,
}: Props) {
  const initials = initialsFromUser(user);
  const verified = Boolean(user?.email_verified_at);
  const busy = resendBusy || refreshBusy;

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
              label={verified ? 'Email verified' : 'Email not verified'}
              tone={verified ? 'success' : 'warning'}
            />
          </View>
        </View>
      </View>

      {!verified && user ? (
        <View style={styles.verifyBlock}>
          <Text style={styles.verifyCopy}>
            Verify your email to secure account recovery. We will send a secure
            link to your inbox (opens on chinaordertz.com).
          </Text>
          {resendMessage ? (
            <Text
              style={styles.verifyMessage}
              accessibilityLiveRegion="polite"
            >
              {resendMessage}
            </Text>
          ) : null}
          <View style={styles.verifyActions}>
            {onResendVerification ? (
              <Pressable
                style={[styles.verifyButton, busy ? styles.verifyButtonDisabled : null]}
                onPress={onResendVerification}
                disabled={busy}
                accessibilityRole="button"
                accessibilityLabel="Resend verification email"
              >
                {resendBusy ? (
                  <ActivityIndicator color={colors.onPrimary} />
                ) : (
                  <Text style={styles.verifyButtonText}>Resend verification email</Text>
                )}
              </Pressable>
            ) : null}
            {onRefreshVerification ? (
              <Pressable
                style={styles.refreshLink}
                onPress={onRefreshVerification}
                disabled={busy}
                accessibilityRole="button"
                accessibilityLabel="I already verified — check again"
              >
                {refreshBusy ? (
                  <ActivityIndicator color={colors.primaryPressed} />
                ) : (
                  <Text style={styles.refreshLinkText}>
                    I already verified — check again
                  </Text>
                )}
              </Pressable>
            ) : null}
          </View>
        </View>
      ) : null}
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
  verifyBlock: {
    marginTop: spacing.md,
    paddingTop: spacing.md,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
  },
  verifyCopy: {
    ...typography.caption,
    color: colors.textSecondary,
    marginBottom: spacing.sm,
  },
  verifyMessage: {
    ...typography.caption,
    color: colors.success,
    marginBottom: spacing.sm,
  },
  verifyActions: {
    gap: spacing.sm,
  },
  verifyButton: {
    backgroundColor: colors.primary,
    borderRadius: radius.md,
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
    alignItems: 'center',
    minHeight: 44,
    justifyContent: 'center',
  },
  verifyButtonDisabled: {
    opacity: 0.6,
  },
  verifyButtonText: {
    ...typography.bodyStrong,
    color: colors.onPrimary,
    fontWeight: '700',
  },
  refreshLink: {
    alignItems: 'center',
    paddingVertical: spacing.xs,
    minHeight: 44,
    justifyContent: 'center',
  },
  refreshLinkText: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '600',
  },
});
