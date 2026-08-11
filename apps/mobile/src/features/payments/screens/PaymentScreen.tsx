import { useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useQueryClient } from '@tanstack/react-query';
import { clearSessionOnAuthFailure, useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { invalidateAfterPaymentSuccess } from '@/src/features/orders/hooks/useOrders';
import { buildPostPaymentOrdersHref } from '@/src/features/orders/utils/orderRoutes';
import {
  refreshPaymentTransaction,
  retryNmbCheckoutSession,
} from '../api/paymentsApi';
import { PaymentStatusCard } from '../components/PaymentStatusCard';
import { usePaymentMethods } from '../hooks/usePayments';
import type { PaymentOrder, PaymentTransaction } from '../models/types';
import { handleNmbPaymentReturn } from '../utils/handlePaymentReturn';
import {
  isSuccessfulPaymentStatus,
  isTerminalPaymentStatus,
} from '../utils/mapPayment';
import { launchNmbCheckoutForTransaction } from '../utils/nmbBrowser';
import { buildPaymentHref } from '../utils/paymentRoutes';
import { payOrderWithNmb } from '../utils/payWithNmb';
import {
  clearPaymentAndCheckoutContexts,
  handOffCheckoutToPayment,
} from '../utils/recoveryHandoff';
import {
  getPaymentErrorMessage,
  isPaymentUnauthenticatedError,
} from '../utils/paymentErrorMessage';

function firstParam(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

export function PaymentScreen() {
  const authStatus = useAuthStore((s) => s.status);
  const queryClient = useQueryClient();
  const params = useLocalSearchParams<{
    checkoutSessionId?: string | string[];
    orderId?: string | string[];
    paymentTransactionId?: string | string[];
  }>();

  const checkoutSessionId = firstParam(params.checkoutSessionId);
  const orderIdParam = firstParam(params.orderId);
  const paymentTransactionIdParam = firstParam(params.paymentTransactionId);

  const methodsQuery = usePaymentMethods(authStatus === 'authenticated');
  const [order, setOrder] = useState<PaymentOrder | null>(null);
  const [transaction, setTransaction] = useState<PaymentTransaction | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const restoredRef = useRef(false);

  const paymentReturnHref = buildPaymentHref({
    orderId: orderIdParam ?? order?.id,
    checkoutSessionId,
    paymentTransactionId: paymentTransactionIdParam ?? transaction?.id,
  });

  const nmbSelectable = methodsQuery.data?.methods.find(
    (method) => method.code === 'nmb' && method.selectable,
  );

  useEffect(() => {
    if (authStatus !== 'authenticated' || restoredRef.current) return;
    if (!paymentTransactionIdParam && !orderIdParam) return;
    restoredRef.current = true;

    void (async () => {
      setBusy(true);
      setError(null);
      try {
        const handled = await handleNmbPaymentReturn({
          orderId: orderIdParam,
          paymentTransactionId: paymentTransactionIdParam,
        });
        if (handled.transaction) {
          setTransaction(handled.transaction);
          if (handled.orderId) {
            setOrder({
              id: handled.orderId,
              orderNumber: handled.transaction.order?.orderNumber ?? null,
              status: handled.transaction.order?.status ?? null,
              currency: handled.transaction.currency,
              grandTotal: handled.transaction.order?.grandTotal ?? null,
              checkoutSessionId: checkoutSessionId ?? null,
            });
          }
          if (isSuccessfulPaymentStatus(handled.transaction.status)) {
            await invalidateAfterPaymentSuccess(
              queryClient,
              handled.orderId,
            );
          }
        }
      } catch (err) {
        if (isPaymentUnauthenticatedError(err)) {
          await clearSessionOnAuthFailure();
          router.push(buildLoginHref(paymentReturnHref));
          return;
        }
        setError(getPaymentErrorMessage(err));
      } finally {
        setBusy(false);
      }
    })();
  }, [
    authStatus,
    checkoutSessionId,
    orderIdParam,
    paymentTransactionIdParam,
    paymentReturnHref,
    queryClient,
  ]);

  async function redirectToLogin() {
    await clearSessionOnAuthFailure();
    router.push(buildLoginHref(paymentReturnHref));
  }

  async function runPaymentFlow() {
    setError(null);
    setBusy(true);
    try {
      const result = await payOrderWithNmb({
        checkoutSessionId,
        orderId: orderIdParam ?? order?.id,
        provider: nmbSelectable?.code ?? 'nmb',
        existingTransaction: null,
      });
      setOrder(result.order);
      setTransaction(result.transaction);
      if (isSuccessfulPaymentStatus(result.transaction.status)) {
        await invalidateAfterPaymentSuccess(queryClient, result.order.id);
        await clearPaymentAndCheckoutContexts();
      }
    } catch (err) {
      if (isPaymentUnauthenticatedError(err)) {
        redirectToLogin();
        return;
      }
      setError(getPaymentErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function refreshStatus() {
    if (!transaction?.id) return;
    setBusy(true);
    setError(null);
    try {
      const next = await refreshPaymentTransaction(transaction.id);
      setTransaction(next);
      if (isSuccessfulPaymentStatus(next.status)) {
        await invalidateAfterPaymentSuccess(
          queryClient,
          next.orderId || orderIdParam,
        );
        await clearPaymentAndCheckoutContexts();
      }
    } catch (err) {
      if (isPaymentUnauthenticatedError(err)) {
        redirectToLogin();
        return;
      }
      setError(getPaymentErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function retryCheckout() {
    if (!transaction?.id) {
      await runPaymentFlow();
      return;
    }
    setBusy(true);
    setError(null);
    try {
      let next = await retryNmbCheckoutSession(transaction.id);
      await handOffCheckoutToPayment({
        orderId: next.orderId || order?.id || orderIdParam || null,
        paymentTransactionId: next.id,
        merchantReference: next.merchantReference,
        successIndicator: next.successIndicator,
        checkoutSessionId: checkoutSessionId ?? null,
      });

      const browser = await launchNmbCheckoutForTransaction(next);
      const handled = await handleNmbPaymentReturn({
        returnUrl: browser.url,
        orderId: next.orderId || order?.id || orderIdParam,
        paymentTransactionId: next.id,
        merchantReference: next.merchantReference,
        resultIndicator: browser.returnParams.resultIndicator,
      });
      next = handled.transaction ?? (await refreshPaymentTransaction(next.id));
      setTransaction(next);
      if (isSuccessfulPaymentStatus(next.status)) {
        await invalidateAfterPaymentSuccess(
          queryClient,
          next.orderId || orderIdParam,
        );
        await clearPaymentAndCheckoutContexts();
      }
    } catch (err) {
      if (isPaymentUnauthenticatedError(err)) {
        redirectToLogin();
        return;
      }
      setError(getPaymentErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  if (authStatus !== 'authenticated') {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Payment</Text>
        <Text style={styles.body}>Please sign in to continue payment.</Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() => router.push(buildLoginHref(paymentReturnHref))}
        >
          <Text style={styles.primaryButtonText}>Sign in</Text>
        </Pressable>
      </View>
    );
  }

  if (
    !checkoutSessionId &&
    !orderIdParam &&
    !paymentTransactionIdParam &&
    !transaction
  ) {
    return (
      <View style={styles.centered}>
        <Text style={styles.title}>Payment</Text>
        <Text style={styles.body}>
          Start checkout and select shipping before continuing to payment.
        </Text>
        <Pressable
          style={styles.primaryButton}
          onPress={() => router.replace('/(app)/checkout')}
        >
          <Text style={styles.primaryButtonText}>Go to checkout</Text>
        </Pressable>
      </View>
    );
  }

  const paid = isSuccessfulPaymentStatus(transaction?.status);
  const terminal = isTerminalPaymentStatus(transaction?.status);
  const confirmedOrderId =
    transaction?.orderId ||
    transaction?.order?.id ||
    order?.id ||
    orderIdParam ||
    null;

  async function goToOrdersAfterPayment() {
    await invalidateAfterPaymentSuccess(queryClient, confirmedOrderId);
    await clearPaymentAndCheckoutContexts();
    router.replace(buildPostPaymentOrdersHref(confirmedOrderId));
  }

  return (
    <ScrollView style={styles.screen} contentContainerStyle={styles.content}>
      <Text style={styles.subheading}>
        Pay securely with NMB Hosted Checkout. Status is confirmed by the server only.
      </Text>

      {methodsQuery.data ? (
        <Text style={styles.meta}>Payment method: NMB</Text>
      ) : null}

      {error ? <Text style={styles.error}>{error}</Text> : null}

      {transaction ? <PaymentStatusCard transaction={transaction} /> : null}

      {order?.orderNumber ? (
        <Text style={styles.meta}>Order {order.orderNumber}</Text>
      ) : null}

      {paid ? (
        <View style={styles.successBox}>
          <Text style={styles.successTitle}>Payment confirmed</Text>
          <Text style={styles.body}>
            Server confirmed this payment. View your order for status and tracking.
          </Text>
          <Pressable
            style={styles.primaryButton}
            onPress={() => void goToOrdersAfterPayment()}
          >
            <Text style={styles.primaryButtonText}>
              {confirmedOrderId ? 'View order' : 'View my orders'}
            </Text>
          </Pressable>
        </View>
      ) : (
        <View style={styles.actions}>
          <Pressable
            style={[styles.primaryButton, busy ? styles.disabled : null]}
            disabled={busy || methodsQuery.isLoading}
            onPress={() => void (transaction ? retryCheckout() : runPaymentFlow())}
          >
            {busy ? (
              <ActivityIndicator color="#fff" />
            ) : (
              <Text style={styles.primaryButtonText}>
                {transaction ? 'Retry NMB checkout' : 'Pay with NMB'}
              </Text>
            )}
          </Pressable>

          {transaction ? (
            <Pressable
              style={[styles.secondaryButton, busy ? styles.disabled : null]}
              disabled={busy}
              onPress={() => void refreshStatus()}
            >
              <Text style={styles.secondaryButtonText}>Refresh status</Text>
            </Pressable>
          ) : null}

          {terminal && !paid ? (
            <Text style={styles.note}>
              Payment was not completed. You can retry or return to checkout.
            </Text>
          ) : null}
        </View>
      )}
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  screen: { flex: 1, backgroundColor: '#fff' },
  content: { padding: 16, paddingBottom: 40 },
  centered: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    padding: 24,
    backgroundColor: '#fff',
    gap: 10,
  },
  heading: { fontSize: 22, fontWeight: '700', color: '#111' },
  title: { fontSize: 18, fontWeight: '700', color: '#222' },
  subheading: {
    marginTop: 6,
    marginBottom: 12,
    fontSize: 13,
    color: '#666',
    lineHeight: 18,
  },
  body: { fontSize: 14, color: '#666', textAlign: 'center' },
  meta: { fontSize: 13, color: '#555', marginBottom: 8 },
  error: { color: '#b00020', marginBottom: 12, fontSize: 14 },
  note: { marginTop: 10, fontSize: 13, color: '#666', textAlign: 'center' },
  actions: { marginTop: 16 },
  successBox: { marginTop: 20, alignItems: 'center', gap: 8 },
  successTitle: { fontSize: 18, fontWeight: '700', color: '#1b7f3a' },
  primaryButton: {
    marginTop: 12,
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
