import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { canSubmitInFlightAction } from '@/src/core/async/inFlightGuard';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { TrustStrip } from '@/src/shared/ui/TrustStrip';
import { colors, spacing, typography } from '@/src/shared/theme';
import { fetchCheckoutSession } from '../api/checkoutApi';
import { CheckoutItemsList } from '../components/CheckoutItemsList';
import {
  CheckoutProgress,
  type CheckoutProgressStep,
} from '../components/CheckoutProgress';
import { CheckoutTotals } from '../components/CheckoutTotals';
import { ContinueToPaymentButton } from '../components/ContinueToPaymentButton';
import { DeliveryAddressForm } from '../components/DeliveryAddressForm';
import { ShippingChoicePicker } from '../components/ShippingChoicePicker';
import {
  useApplyShippingChoiceMutation,
  useCancelCheckoutSessionMutation,
  useCheckoutPrepare,
  useRefreshCheckoutSessionMutation,
  useStartCheckoutSessionMutation,
  useUpdateDeliveryAddressMutation,
} from '../hooks/useCheckout';
import {
  isCompletedCheckoutSessionCancelError,
  shouldCancelCheckoutSession,
} from '../utils/cancelCheckoutSession';
import type { CheckoutSession } from '../models/types';
import {
  isRecoverableCheckoutSession,
  pendingCheckoutContextStorage,
} from '../storage/pendingCheckoutContextStorage';
import {
  getCheckoutErrorMessage,
  isEmptyCartCheckoutError,
  isMissingDeliveryAddressError,
} from '../utils/checkoutErrorMessage';
import {
  checkoutSessionStatusLabel,
  checkoutShippingChoiceLabel,
  checkoutShippingMethodLabel,
} from '../utils/checkoutDisplayLabels';
import {
  isReadyForPayment,
  isStaleOrExpiredCheckoutError,
  journeyLabelFromCheckoutItems,
} from '../utils/mapCheckout';

type RecoveryOffer = {
  checkoutSessionId: string;
};

export function CheckoutScreen() {
  const authStatus = useAuthStore((s) => s.status);
  const prepareQuery = useCheckoutPrepare(authStatus === 'authenticated');
  const startMutation = useStartCheckoutSessionMutation();
  const refreshMutation = useRefreshCheckoutSessionMutation();
  const shippingMutation = useApplyShippingChoiceMutation();
  const addressMutation = useUpdateDeliveryAddressMutation();
  const cancelMutation = useCancelCheckoutSessionMutation();

  const [session, setSessionState] = useState<CheckoutSession | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [needsAddress, setNeedsAddress] = useState(false);
  const [recoveryOffer, setRecoveryOffer] = useState<RecoveryOffer | null>(null);
  const [recoveryBusy, setRecoveryBusy] = useState(false);
  const [recoveryHydrated, setRecoveryHydrated] = useState(false);

  const persistSession = useCallback(async (next: CheckoutSession | null) => {
    if (!next?.id || !isRecoverableCheckoutSession(next)) {
      await pendingCheckoutContextStorage.clear();
      return;
    }
    const userId = useAuthStore.getState().user?.id ?? null;
    await pendingCheckoutContextStorage.save({
      userId,
      checkoutSessionId: next.id,
      orderId: null,
      paymentTransactionId: null,
    });
  }, []);

  const setSession = useCallback(
    (next: CheckoutSession | null) => {
      setSessionState(next);
      void persistSession(next);
    },
    [persistSession],
  );

  useEffect(() => {
    if (authStatus !== 'authenticated') {
      return;
    }

    let cancelled = false;
    void (async () => {
      try {
        const context = await pendingCheckoutContextStorage.readValid();
        if (cancelled) return;
        const userId = useAuthStore.getState().user?.id ?? null;
        if (
          context?.checkoutSessionId &&
          context.userId &&
          userId &&
          context.userId === userId
        ) {
          setRecoveryOffer({ checkoutSessionId: context.checkoutSessionId });
        }
      } finally {
        if (!cancelled) setRecoveryHydrated(true);
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [authStatus]);

  const recoveryChecked =
    authStatus !== 'authenticated' || recoveryHydrated;

  const prepare = prepareQuery.data;
  const shippingOptions = useMemo(
    () => prepare?.shippingChoices ?? [],
    [prepare?.shippingChoices],
  );
  const journeyLabel = journeyLabelFromCheckoutItems(prepare?.items ?? []);
  const readyForPayment = isReadyForPayment(session);

  const progressStep: CheckoutProgressStep = !session
    ? 'review'
    : readyForPayment
      ? 'payment'
      : 'shipping';

  async function continueRecoveredCheckout(sessionId: string) {
    if (
      !canSubmitInFlightAction(
        recoveryBusy || startMutation.isPending || cancelMutation.isPending,
      )
    ) {
      return;
    }
    setRecoveryBusy(true);
    setActionError(null);
    try {
      const restored = await fetchCheckoutSession(sessionId);
      if (!isRecoverableCheckoutSession(restored)) {
        await pendingCheckoutContextStorage.clear();
        setRecoveryOffer(null);
        setActionError(
          'Your previous checkout is no longer available. Please start again.',
        );
        return;
      }
      setSession(restored);
      setRecoveryOffer(null);
    } catch (err) {
      await pendingCheckoutContextStorage.clear();
      setRecoveryOffer(null);
      setActionError(getCheckoutErrorMessage(err));
    } finally {
      setRecoveryBusy(false);
    }
  }

  async function discardRecoveredCheckout() {
    if (!canSubmitInFlightAction(recoveryBusy || cancelMutation.isPending)) {
      return;
    }
    const sessionId = recoveryOffer?.checkoutSessionId;
    const stored = await pendingCheckoutContextStorage.read();
    if (
      sessionId &&
      shouldCancelCheckoutSession({
        sessionId,
        orderId: stored?.orderId,
      })
    ) {
      try {
        await cancelMutation.mutateAsync(sessionId);
      } catch (err) {
        if (isCompletedCheckoutSessionCancelError(err)) {
          await pendingCheckoutContextStorage.clear();
          setRecoveryOffer(null);
          setSessionState(null);
          return;
        }
        setActionError(getCheckoutErrorMessage(err));
        return;
      }
    }
    await pendingCheckoutContextStorage.clear();
    setRecoveryOffer(null);
    setSessionState(null);
  }

  async function abandonActiveCheckout() {
    if (!session?.id) return;
    if (
      !canSubmitInFlightAction(
        startMutation.isPending ||
          shippingMutation.isPending ||
          cancelMutation.isPending ||
          recoveryBusy,
      )
    ) {
      return;
    }
    setActionError(null);
    if (
      shouldCancelCheckoutSession({
        sessionId: session.id,
        sessionStatus: session.status,
      })
    ) {
      try {
        await cancelMutation.mutateAsync(session.id);
      } catch (err) {
        setActionError(getCheckoutErrorMessage(err));
        return;
      }
    }
    setSession(null);
    router.replace('/(app)/(tabs)/cart');
  }

  async function restartExpiredCheckout() {
    if (!session?.id) return;
    setActionError(null);
    if (
      shouldCancelCheckoutSession({
        sessionId: session.id,
        sessionStatus: session.status,
      })
    ) {
      try {
        await cancelMutation.mutateAsync(session.id);
      } catch (err) {
        if (isCompletedCheckoutSessionCancelError(err)) {
          setActionError(getCheckoutErrorMessage(err));
          return;
        }
        setActionError(getCheckoutErrorMessage(err));
        return;
      }
    }
    setSession(null);
    startMutation.mutate(undefined, {
      onSuccess: setSession,
      onError: (err) => setActionError(getCheckoutErrorMessage(err)),
    });
  }

  if (authStatus !== 'authenticated') {
    return (
      <EmptyState
        title="Checkout"
        message="Please sign in to continue checkout."
        actionLabel="Sign in"
        onActionPress={() => router.push(buildLoginHref('/(app)/checkout'))}
        style={styles.fill}
      />
    );
  }

  if (!recoveryChecked || (prepareQuery.isLoading && !prepare)) {
    return <ScreenLoadingState label="Preparing checkout…" />;
  }

  if (prepareQuery.isError && !prepare) {
    const error = prepareQuery.error;
    if (isMissingDeliveryAddressError(error) || needsAddress) {
      return (
        <ScreenContainer padded={false} style={styles.screen}>
          <ScrollView contentContainerStyle={styles.content}>
            <Text style={styles.eyebrow}>Checkout</Text>
            <Text style={styles.heading}>Add delivery address</Text>
            <Text style={styles.subheading}>{getCheckoutErrorMessage(error)}</Text>
            <DeliveryAddressForm
              submitting={addressMutation.isPending}
              onSubmit={(input) => {
                setActionError(null);
                addressMutation.mutate(input, {
                  onSuccess: () => {
                    setNeedsAddress(false);
                    void prepareQuery.refetch();
                  },
                  onError: (err) => setActionError(getCheckoutErrorMessage(err)),
                });
              }}
            />
            {actionError ? <Text style={styles.error}>{actionError}</Text> : null}
          </ScrollView>
        </ScreenContainer>
      );
    }

    if (isEmptyCartCheckoutError(error)) {
      return (
        <EmptyState
          title="Cart is empty"
          message="Add products before starting checkout."
          actionLabel="Back to cart"
          onActionPress={() => router.replace('/(app)/(tabs)/cart')}
          style={styles.fill}
        />
      );
    }

    return (
      <EmptyState
        title="Checkout unavailable"
        message={getCheckoutErrorMessage(error)}
        actionLabel="Retry"
        onActionPress={() => {
          if (isMissingDeliveryAddressError(error)) {
            setNeedsAddress(true);
          }
          void prepareQuery.refetch();
        }}
        style={styles.fill}
      />
    );
  }

  if (!prepare || prepare.items.length === 0) {
    return (
      <EmptyState
        title="Cart is empty"
        message="Add products before starting checkout."
        actionLabel="Back to cart"
        onActionPress={() => router.replace('/(app)/(tabs)/cart')}
        style={styles.fill}
      />
    );
  }

  const address = prepare.deliveryAddress;
  const busy =
    startMutation.isPending ||
    refreshMutation.isPending ||
    shippingMutation.isPending ||
    cancelMutation.isPending ||
    recoveryBusy;

  return (
    <ScreenContainer padded={false} style={styles.screen}>
      <ScrollView
        contentContainerStyle={styles.content}
        refreshControl={
          <RefreshControl
            refreshing={prepareQuery.isRefetching}
            onRefresh={() => {
              void prepareQuery.refetch();
              if (session?.id) {
                refreshMutation.mutate(session.id, {
                  onSuccess: setSession,
                  onError: (err) => {
                    setActionError(getCheckoutErrorMessage(err));
                    if (isStaleOrExpiredCheckoutError(err)) {
                      setSession(null);
                    }
                  },
                });
              }
            }}
            tintColor={colors.primary}
            colors={[colors.primary]}
          />
        }
      >
        <Text style={styles.eyebrow}>Checkout</Text>
        <Text style={styles.heading}>Review & ship</Text>
        <Badge label={journeyLabel} tone="brand" style={styles.journeyBadge} />
        <Text style={styles.subheading}>
          Review your order. Prices and shipping options are confirmed at checkout.
        </Text>

        <CheckoutProgress current={progressStep} />

        {actionError ? <Text style={styles.error}>{actionError}</Text> : null}

        {recoveryOffer && !session ? (
          <Card elevated style={styles.recoveryCard}>
            <Text style={styles.cardTitle}>Continue checkout?</Text>
            <Text style={styles.meta}>
              You have an unfinished checkout. Would you like to continue?
            </Text>
            <PrimaryButton
              label="Continue checkout"
              loading={recoveryBusy}
              disabled={recoveryBusy}
              onPress={() => void continueRecoveredCheckout(recoveryOffer.checkoutSessionId)}
              style={styles.inlineButton}
            />
            <SecondaryButton
              label="Start fresh"
              disabled={recoveryBusy}
              onPress={() => void discardRecoveredCheckout()}
              style={styles.inlineButton}
            />
          </Card>
        ) : null}

        <Card elevated={false} style={styles.addressCard}>
          <Text style={styles.cardTitle}>Delivery address</Text>
          <Text style={styles.meta}>
            {address.recipientName} · {address.phone}
          </Text>
          <Text style={styles.meta}>
            {[address.street, address.district, address.city, address.region, address.country]
              .filter(Boolean)
              .join(', ')}
          </Text>
        </Card>

        <CheckoutItemsList items={prepare.items} />
        <CheckoutTotals prepare={prepare} session={session} />

        {!session ? (
          <PrimaryButton
            label="Continue to checkout"
            loading={startMutation.isPending}
            disabled={busy || Boolean(recoveryOffer)}
            onPress={() => {
              if (!canSubmitInFlightAction(busy)) return;
              setActionError(null);
              startMutation.mutate(undefined, {
                onSuccess: setSession,
                onError: (err) => setActionError(getCheckoutErrorMessage(err)),
              });
            }}
            style={styles.inlineButton}
          />
        ) : (
          <Card elevated={false} style={styles.sessionBox}>
            <Text style={styles.cardTitle}>Your checkout</Text>
            <View style={styles.badgeRow}>
              <Badge label={checkoutSessionStatusLabel(session.status)} tone="info" />
              {checkoutShippingChoiceLabel(session.shippingChoice) ? (
                <Badge
                  label={checkoutShippingChoiceLabel(session.shippingChoice)!}
                  tone="neutral"
                />
              ) : null}
            </View>
            {session.expiresAt ? (
              <Text style={styles.meta}>Expires: {session.expiresAt}</Text>
            ) : null}
            {session.shippingChoice ? (
              <Text style={styles.meta}>
                Choice: {checkoutShippingChoiceLabel(session.shippingChoice)}
                {checkoutShippingMethodLabel(session.shippingMethod)
                  ? ` (${checkoutShippingMethodLabel(session.shippingMethod)})`
                  : ''}
              </Text>
            ) : null}
            {session.isExpired || session.status === 'expired' ? (
              <Text style={styles.error}>
                This checkout timed out. Start again to continue.
              </Text>
            ) : null}

            <SecondaryButton
              label="Update totals"
              disabled={busy}
              onPress={() => {
                setActionError(null);
                refreshMutation.mutate(session.id, {
                  onSuccess: setSession,
                  onError: (err) => {
                    setActionError(getCheckoutErrorMessage(err));
                    if (isStaleOrExpiredCheckoutError(err)) {
                      setSession(null);
                    }
                  },
                });
              }}
              style={styles.inlineButton}
            />

            {session.status !== 'completed' ? (
              <SecondaryButton
                label="Cancel checkout"
                disabled={busy}
                onPress={() => void abandonActiveCheckout()}
                style={styles.inlineButton}
              />
            ) : null}

            {!session.isExpired && session.status !== 'expired' ? (
              <ShippingChoicePicker
                options={shippingOptions}
                submitting={shippingMutation.isPending}
                currentChoice={session.shippingChoice}
                currentMethod={session.shippingMethod}
                onSubmit={(input) => {
                  if (!canSubmitInFlightAction(busy)) return;
                  setActionError(null);
                  shippingMutation.mutate(
                    { sessionId: session.id, ...input },
                    {
                      onSuccess: setSession,
                      onError: (err) => {
                        setActionError(getCheckoutErrorMessage(err));
                        if (isStaleOrExpiredCheckoutError(err)) {
                          setSession(null);
                        }
                      },
                    },
                  );
                }}
              />
            ) : (
              <PrimaryButton
                label="Restart checkout"
                disabled={busy}
                onPress={() => void restartExpiredCheckout()}
                style={styles.inlineButton}
              />
            )}
          </Card>
        )}

        <ContinueToPaymentButton
          enabled={readyForPayment && !busy}
          checkoutSessionId={session?.id}
        />

        <TrustStrip
          title="Secure checkout"
          items={[
            {
              id: 'totals',
              title: 'Confirmed totals',
              description: 'Shipping and total are confirmed at checkout.',
            },
            {
              id: 'payment',
              title: 'Secure payment',
              description:
                'Choose a payment method next. Payment is confirmed before your order is processed.',
            },
          ]}
        />
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
  fill: { flex: 1, backgroundColor: colors.background },
  eyebrow: {
    ...typography.caption,
    color: colors.primaryPressed,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.8,
    marginBottom: spacing.xs,
  },
  heading: { ...typography.heading },
  journeyBadge: { alignSelf: 'flex-start', marginTop: spacing.sm },
  subheading: {
    marginTop: spacing.sm,
    marginBottom: spacing.md,
    ...typography.caption,
  },
  error: { marginVertical: spacing.sm, ...typography.body, color: colors.error },
  addressCard: {
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
    marginBottom: spacing.md,
  },
  recoveryCard: {
    backgroundColor: colors.surfaceCream,
    borderColor: colors.primary,
    marginBottom: spacing.md,
    gap: spacing.sm,
  },
  cardTitle: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    marginBottom: spacing.xs,
  },
  meta: { ...typography.caption, marginTop: spacing.xxs },
  sessionBox: {
    marginTop: spacing.lg,
    backgroundColor: colors.backgroundMuted,
    borderColor: colors.border,
  },
  badgeRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: spacing.xs,
    marginBottom: spacing.sm,
  },
  inlineButton: {
    marginTop: spacing.md,
    alignSelf: 'stretch',
  },
});
