import { useState } from 'react';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { useAuthStore, useJourneyStore } from '@/src/core/auth';
import { logout } from '@/src/features/auth';
import { journeyLabelFromChannel } from '@/src/features/cart/utils/journeyLabel';
import { buildOrdersListHref } from '@/src/features/orders/utils/orderRoutes';
import { BRAND_NAME } from '@/src/shared/branding';
import { Card } from '@/src/shared/ui/Card';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { TrustStrip } from '@/src/shared/ui/TrustStrip';
import { colors, spacing, typography } from '@/src/shared/theme';
import { AccountMenuCard } from './AccountMenuCard';
import { CustomerIdentityCard } from './CustomerIdentityCard';
import {
  buildAccountWebUrl,
  openAccountWebPage,
  type AccountWebPath,
} from '../utils/accountWebLinks';

async function openWebOrAlert(path: AccountWebPath) {
  try {
    await openAccountWebPage(path);
  } catch {
    Alert.alert(
      'Unable to open',
      `Visit ${buildAccountWebUrl(path)} in your browser to continue.`,
    );
  }
}

export function AccountHubScreen() {
  const user = useAuthStore((s) => s.user);
  const journey = useJourneyStore((s) => s.journey);
  const [busy, setBusy] = useState(false);

  async function onLogout() {
    setBusy(true);
    try {
      await logout();
      router.replace('/(auth)/login');
    } finally {
      setBusy(false);
    }
  }

  return (
    <ScreenContainer padded={false} style={styles.screen}>
      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.eyebrow}>Account</Text>
        <Text style={styles.heading}>Customer hub</Text>
        <Text style={styles.subheading}>
          Manage your {BRAND_NAME} shopping experience.
        </Text>

        <CustomerIdentityCard user={user} />

        <Card elevated style={styles.ordersShortcut}>
          <Text style={styles.sectionLabel}>Quick actions</Text>
          <Text style={styles.shortcutTitle}>My Orders</Text>
          <Text style={styles.shortcutBody}>
            Track purchases, continue payment, and view delivery progress.
          </Text>
          <PrimaryButton
            label="View orders"
            onPress={() => router.push(buildOrdersListHref() as never)}
            style={styles.shortcutButton}
          />
          <SecondaryButton
            label={`Shopping as · ${journeyLabelFromChannel(journey)}`}
            onPress={() => router.push('/(app)/(tabs)/home')}
            style={styles.shortcutButton}
          />
        </Card>

        <Text style={styles.sectionLabel}>Account</Text>
        <AccountMenuCard
          title="Addresses"
          description="Delivery addresses are managed securely on the storefront."
          badge="Web"
          onPress={() => void openWebOrAlert('/account/addresses')}
        />
        <AccountMenuCard
          title="Security"
          description="Password and account security settings on the website."
          badge="Web"
          onPress={() => void openWebOrAlert('/account/security')}
        />
        <AccountMenuCard
          title="Notifications"
          description="Review notification preferences on the storefront."
          badge="Web"
          onPress={() => void openWebOrAlert('/account/notifications')}
        />

        <Text style={styles.sectionLabel}>Help</Text>
        <AccountMenuCard
          title="Support"
          description="Open storefront support — contact details come from the website."
          badge="Web"
          onPress={() => void openWebOrAlert('/account/support')}
        />
        <AccountMenuCard
          title="Settings"
          description="Browse the storefront account page for additional preferences."
          badge="Web"
          onPress={() => void openWebOrAlert('/account')}
        />

        <TrustStrip
          title="Shopping with confidence"
          items={[
            {
              id: 'orders',
              title: 'Server-owned order status',
              description:
                'Payment and fulfillment status always come from the API — never guessed on device.',
            },
            {
              id: 'support',
              title: 'Official storefront support',
              description:
                'Support entry points open the configured CHINA ORDER TZ website only.',
            },
          ]}
        />

        <View style={styles.logoutWrap}>
          <PrimaryButton
            label="Log out"
            loading={busy}
            disabled={busy}
            onPress={() => void onLogout()}
            style={styles.logout}
          />
        </View>
      </ScrollView>
    </ScreenContainer>
  );
}

const styles = StyleSheet.create({
  screen: { backgroundColor: colors.background },
  content: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.lg,
    paddingBottom: spacing.huge,
  },
  eyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: spacing.xs,
  },
  heading: { ...typography.heading },
  subheading: {
    ...typography.caption,
    marginTop: spacing.xs,
    marginBottom: spacing.lg,
  },
  sectionLabel: {
    ...typography.caption,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    color: colors.textMuted,
    marginTop: spacing.md,
    marginBottom: spacing.sm,
  },
  ordersShortcut: {
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
    marginBottom: spacing.md,
  },
  shortcutTitle: {
    ...typography.title,
    fontSize: 17,
    marginBottom: spacing.xs,
  },
  shortcutBody: {
    ...typography.caption,
    marginBottom: spacing.md,
  },
  shortcutButton: {
    alignSelf: 'stretch',
    marginTop: spacing.sm,
  },
  logoutWrap: {
    marginTop: spacing.xxl,
    paddingTop: spacing.lg,
    borderTopWidth: StyleSheet.hairlineWidth,
    borderTopColor: colors.border,
  },
  logout: {
    alignSelf: 'stretch',
    backgroundColor: colors.error,
  },
});
