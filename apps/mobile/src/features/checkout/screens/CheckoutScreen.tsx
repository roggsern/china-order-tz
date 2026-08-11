import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { fetchCheckoutSession } from '../api/checkoutApi';
import { CheckoutItemsList } from '../components/CheckoutItemsList';
import { CheckoutTotals } from '../components/CheckoutTotals';
import { ContinueToPaymentButton } from '../components/ContinueToPaymentButton';
import { DeliveryAddressForm } from '../components/DeliveryAddressForm';
import { ShippingChoicePicker } from '../components/ShippingChoicePicker';
import {
  useApplyShippingChoiceMutation,
  useCheckoutPrepare,
  useRefreshCheckoutSessionMutation,
  useStartCheckoutSessionMutation,
  useUpdateDeliveryAddressMutation,
} from '../hooks/useCheckout';
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
  isReadyForPayment,
  isStaleOrExpiredCheckoutError,
  journeyLabelFromCheckoutItems,
  shippingChoicesForItems,
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
    () => shippingChoicesForItems(prepare?.items ?? []),
    [prepare?.items],
  );
  const journeyLabel = journeyLabelFromCheckoutItems(prepare?.items ?? []);
  const readyForPayment = isReadyForPayment(session);

  async function continueRecoveredCheckout(sessionId: string) {
    setRecoveryBusy(true);
    setActionError(null);
    try {
      const restored = await fetchCheckoutSession(sessionId);
      if (!isRecoverableCheckoutSession(restored)) {
        await pendingCheckoutContextStorage.clear();
        setRecoveryOffer(null);
        setActionError(
          'Previous checkout session is no longer available. Start checkout again.',
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
    await pendingCheckoutContextStorage.clear();
    setRecoveryOffer(null);
    setSessionState(null);
  }

  if (authStatus !== 'authenticated') {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Checkout</Text>
        <Text style={styles.body}>Please sign in to continue checkout.</Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() => router.push(buildLoginHref('/(app)/checkout'))}
        >
          <Text style={styles.primaryButtonText}>Sign in</Text>
        </Pressable>
      </View>
    );
  }

  if (!recoveryChecked || (prepareQuery.isLoading && !prepare)) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator size="large" color="#0a7ea4" />
        <Text style={styles.muted}>Preparing checkout…</Text>
      </View>
    );
  }

  if (prepareQuery.isError && !prepare) {
    const error = prepareQuery.error;
    if (isMissingDeliveryAddressError(error) || needsAddress) {
      return (
        <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
          <Text style={styles.heading}>Checkout</Text>
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
      );
    }

    if (isEmptyCartCheckoutError(error)) {
      return (
        <View style={styles.centered}>
          <Text style={styles.title}>Cart is empty</Text>
          <Text style={styles.body}>Add products before starting checkout.</Text>
          <Pressable
            style={styles.primaryButton}
            onPress={() => router.replace('/(app)/(tabs)/cart')}
          >
            <Text style={styles.primaryButtonText}>Back to cart</Text>
          </Pressable>
        </View>
      );
    }

    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Checkout unavailable</Text>
        <Text style={styles.body}>{getCheckoutErrorMessage(error)}</Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() => {
            if (isMissingDeliveryAddressError(error)) {
              setNeedsAddress(true);
            }
            void prepareQuery.refetch();
          }}
        >
          <Text style={styles.primaryButtonText}>Retry</Text>
        </Pressable>
      </View>
    );
  }

  if (!prepare || prepare.items.length === 0) {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Cart is empty</Text>
        <Text style={styles.body}>Add products before starting checkout.</Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() => router.replace('/(app)/(tabs)/cart')}
        >
          <Text style={styles.primaryButtonText}>Back to cart</Text>
        </Pressable>
      </View>
    );
  }

  const address = prepare.deliveryAddress;
  const busy =
    startMutation.isPending ||
    refreshMutation.isPending ||
    shippingMutation.isPending ||
    recoveryBusy;

  return (
    <ScrollView
      style={styles.screen}
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
        />
      }
    >
      <Text style={styles.journey}>{journeyLabel}</Text>
      <Text style={styles.subheading}>
        Review your order. Prices and shipping rules come from the server.
      </Text>

      {actionError ? <Text style={styles.error}>{actionError}</Text> : null}

      {recoveryOffer && !session ? (
        <View style={styles.recoveryCard}>
          <Text style={styles.cardTitle}>Continue checkout?</Text>
          <Text style={styles.meta}>
            We found an unfinished checkout session from before the app closed.
          </Text>
          <Pressable
            style={[styles.primaryButton, recoveryBusy ? styles.disabled : null]}
            disabled={recoveryBusy}
            onPress={() => void continueRecoveredCheckout(recoveryOffer.checkoutSessionId)}
          >
            {recoveryBusy ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.primaryButtonText}>Continue checkout</Text>
            )}
          </Pressable>
          <Pressable
            style={styles.secondaryButton}
            disabled={recoveryBusy}
            onPress={() => void discardRecoveredCheckout()}
          >
            <Text style={styles.secondaryButtonText}>Start fresh</Text>
          </Pressable>
        </View>
      ) : null}

      <View style={styles.card}>
        <Text style={styles.cardTitle}>Delivery address</Text>
        <Text style={styles.meta}>
          {address.recipientName} · {address.phone}
        </Text>
        <Text style={styles.meta}>
          {[address.street, address.district, address.city, address.region, address.country]
            .filter(Boolean)
            .join(', ')}
        </Text>
      </View>

      <CheckoutItemsList items={prepare.items} />
      <CheckoutTotals prepare={prepare} session={session} />

      {!session ? (
        <Pressable
          style={[
            styles.primaryButton,
            busy || Boolean(recoveryOffer) ? styles.disabled : null,
          ]}
          disabled={busy || Boolean(recoveryOffer)}
          onPress={() => {
            setActionError(null);
            startMutation.mutate(undefined, {
              onSuccess: setSession,
              onError: (err) => setActionError(getCheckoutErrorMessage(err)),
            });
          }}
        >
          {startMutation.isPending ? (
            <ActivityIndicator color="#fff" />
          ) : (
            <Text style={styles.primaryButtonText}>Start checkout session</Text>
          )}
        </Pressable>
      ) : (
        <View style={styles.sessionBox}>
          <Text style={styles.cardTitle}>Checkout session</Text>
          <Text style={styles.meta}>Status: {session.status}</Text>
          {session.expiresAt ? (
            <Text style={styles.meta}>Expires: {session.expiresAt}</Text>
          ) : null}
          {session.shippingChoice ? (
            <Text style={styles.meta}>
              Choice: {session.shippingChoice}
              {session.shippingMethod ? ` (${session.shippingMethod})` : ''}
            </Text>
          ) : null}
          {session.isExpired || session.status === 'expired' ? (
            <Text style={styles.error}>
              Session expired. Refresh or start checkout again.
            </Text>
          ) : null}

          <Pressable
            style={styles.secondaryButton}
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
          >
            <Text style={styles.secondaryButtonText}>Refresh session</Text>
          </Pressable>

          {!session.isExpired && session.status !== 'expired' ? (
            <ShippingChoicePicker
              options={shippingOptions}
              submitting={shippingMutation.isPending}
              currentChoice={session.shippingChoice}
              currentMethod={session.shippingMethod}
              onSubmit={(input) => {
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
            <Pressable
              style={styles.primaryButton}
              onPress={() => {
                setSession(null);
                setActionError(null);
                startMutation.mutate(undefined, {
                  onSuccess: setSession,
                  onError: (err) => setActionError(getCheckoutErrorMessage(err)),
                });
              }}
            >
              <Text style={styles.primaryButtonText}>Restart checkout</Text>
            </Pressable>
          )}
        </View>
      )}

      <ContinueToPaymentButton
        enabled={readyForPayment && !busy}
        checkoutSessionId={session?.id}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#fff' },
  content: { padding: 16, paddingBottom: 48 },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#fff',
    gap: 10,
  },
  heading: { fontSize: 22, fontWeight: '700', color: '#111' },
  journey: {
    marginTop: 4,
    fontSize: 12,
    fontWeight: '700',
    color: '#0a7ea4',
  },
  subheading: {
    marginTop: 6,
    marginBottom: 12,
    fontSize: 13,
    color: '#666',
    lineHeight: 18,
  },
  title: { fontSize: 18, fontWeight: '700', color: '#222' },
  body: { fontSize: 14, color: '#666', textAlign: 'center' },
  muted: { fontSize: 14, color: '#666' },
  error: { marginVertical: 8, color: '#b00020', fontSize: 14 },
  card: {
    padding: 12,
    borderRadius: 10,
    backgroundColor: '#f5f7f8',
    marginBottom: 8,
  },
  recoveryCard: {
    padding: 12,
    borderRadius: 10,
    backgroundColor: '#eef7fa',
    borderWidth: 1,
    borderColor: '#b6d9e6',
    marginBottom: 12,
  },
  cardTitle: { fontSize: 14, fontWeight: '700', color: '#222', marginBottom: 4 },
  meta: { fontSize: 13, color: '#555', marginTop: 2 },
  sessionBox: { marginTop: 16 },
  primaryButton: {
    marginTop: 16,
    backgroundColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  primaryButtonText: { color: '#fff', fontWeight: '700', fontSize: 15 },
  secondaryButton: {
    marginTop: 10,
    borderWidth: 1,
    borderColor: '#0a7ea4',
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  secondaryButtonText: { color: '#0a7ea4', fontWeight: '700' },
  disabled: { opacity: 0.6 },
});
