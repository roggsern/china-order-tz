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
} from '../utils/accountWebLinks';
import { resolveAccountCapability } from '../utils/accountCapabilities';

async function openWebPathOrAlert(
  path: '/account' | '/privacy' | '/terms' | '/delete-account',
) {
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

  function openNative(capabilityId: Parameters<typeof resolveAccountCapability>[0]) {
    const capability = resolveAccountCapability(capabilityId);
    if (capability.decision === 'native' && capability.nativeHref) {
      router.push(capability.nativeHref as never);
    }
  }

  function openWebsiteCapability(
    capabilityId: 'settings' | 'privacy' | 'terms',
  ) {
    const capability = resolveAccountCapability(capabilityId);
    if (capability.decision === 'website' && capability.webPath) {
      void openWebPathOrAlert(capability.webPath);
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
          title="Edit profile"
          description="Update your name and phone in the app."
          onPress={() => openNative('profile')}
        />
        <AccountMenuCard
          title="Addresses"
          description="Manage delivery addresses in the app."
          onPress={() => openNative('addresses')}
        />
        <AccountMenuCard
          title="Wishlist"
          description="Products you saved while shopping."
          onPress={() => openNative('wishlist')}
        />
        <AccountMenuCard
          title="Change password"
          description="Update your password. You will sign in again after a successful change."
          onPress={() => openNative('security_password')}
        />
        <AccountMenuCard
          title="Notifications"
          description="Read your account and order notifications."
          onPress={() => openNative('notifications')}
        />

        <Text style={styles.sectionLabel}>Help</Text>
        <AccountMenuCard
          title="Support"
          description="Create and follow support tickets in the app."
          onPress={() => openNative('support')}
        />
        <AccountMenuCard
          title="More on website"
          description="Additional account preferences on chinaordertz.com."
          badge="Website"
          secondary
          onPress={() => openWebsiteCapability('settings')}
        />

        <Text style={styles.sectionLabel}>Legal</Text>
        <AccountMenuCard
          title="Privacy Policy"
          description="How we handle account, order, and device information."
          badge="Website"
          secondary
          onPress={() => openWebsiteCapability('privacy')}
        />
        <AccountMenuCard
          title="Terms of Service"
          description="Rules for using CHINA ORDER TZ shopping journeys."
          badge="Website"
          secondary
          onPress={() => openWebsiteCapability('terms')}
        />

        <Text style={styles.sectionLabel}>Danger zone</Text>
        <AccountMenuCard
          title="Close account"
          description="End access permanently. Some transaction records may be retained for operational or legal reasons."
          destructive
          onPress={() => openNative('close_account')}
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
              title: 'Native where APIs exist',
              description:
                'Website handoffs remain only for capabilities without a mobile API.',
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
