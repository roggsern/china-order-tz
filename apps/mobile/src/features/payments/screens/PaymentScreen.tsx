import { useEffect, useRef, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import { useQueryClient } from '@tanstack/react-query';
import { clearSessionOnAuthFailure, useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { invalidateAfterPaymentSuccess } from '@/src/features/orders/hooks/useOrders';
import { buildPostPaymentOrdersHref } from '@/src/features/orders/utils/orderRoutes';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { TrustStrip } from '@/src/shared/ui/TrustStrip';
import { colors, spacing, typography } from '@/src/shared/theme';
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
      <EmptyState
        title="Payment"
        message="Please sign in to continue payment."
        actionLabel="Sign in"
        onActionPress={() => router.push(buildLoginHref(paymentReturnHref))}
        style={styles.fill}
      />
    );
  }

  if (
    !checkoutSessionId &&
    !orderIdParam &&
    !paymentTransactionIdParam &&
    !transaction
  ) {
    return (
      <EmptyState
        title="Payment"
        message="Start checkout and select shipping before continuing to payment."
        actionLabel="Go to checkout"
        onActionPress={() => router.replace('/(app)/checkout')}
        style={styles.fill}
      />
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
    <ScreenContainer padded={false} style={styles.screen}>
      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.eyebrow}>Payment</Text>
        <Text style={styles.heading}>NMB Hosted Checkout</Text>
        <Text style={styles.subheading}>
          Pay securely with NMB. Status is confirmed by the server only — never
          guessed from the browser return.
        </Text>

        <Card elevated={false} style={styles.methodCard}>
          <Text style={styles.cardTitle}>Payment method</Text>
          <Badge label="NMB" tone="brand" style={styles.methodBadge} />
          <Text style={styles.meta}>
            You will leave the app briefly to complete bank checkout, then return
            here for confirmation.
          </Text>
        </Card>

        {error ? <Text style={styles.error}>{error}</Text> : null}

        {transaction ? <PaymentStatusCard transaction={transaction} /> : null}

        {order?.orderNumber ? (
          <Text style={styles.meta}>Order {order.orderNumber}</Text>
        ) : null}

        {paid ? (
          <Card elevated style={styles.successBox}>
            <Badge label="Confirmed" tone="success" />
            <Text style={styles.successTitle}>Payment confirmed</Text>
            <Text style={styles.body}>
              Server confirmed this payment. View your order for status and tracking.
            </Text>
            <PrimaryButton
              label={confirmedOrderId ? 'View order' : 'View my orders'}
              onPress={() => void goToOrdersAfterPayment()}
              style={styles.inlineButton}
            />
          </Card>
        ) : (
          <View style={styles.actions}>
            <PrimaryButton
              label={transaction ? 'Retry NMB checkout' : 'Pay with NMB'}
              loading={busy}
              disabled={busy || methodsQuery.isLoading}
              onPress={() => void (transaction ? retryCheckout() : runPaymentFlow())}
              style={styles.inlineButton}
            />

            {transaction ? (
              <SecondaryButton
                label="Refresh status"
                disabled={busy}
                onPress={() => void refreshStatus()}
                style={styles.inlineButton}
              />
            ) : null}

            {terminal && !paid ? (
              <Text style={styles.note}>
                Payment was not completed. You can retry or return to checkout.
              </Text>
            ) : null}
          </View>
        )}

        <TrustStrip
          title="What happens next"
          items={[
            {
              id: 'handoff',
              title: 'Secure bank handoff',
              description:
                'NMB Hosted Checkout opens in your system browser for card or mobile money payment.',
            },
            {
              id: 'return',
              title: 'Return & confirm',
              description:
                'After payment, return to the app. Only a successful server refresh marks the order paid.',
            },
            {
              id: 'retry',
              title: 'Safe to retry',
              description:
                'If the browser closes early, use Retry or Refresh status — never assume unpaid means failed.',
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
  subheading: {
    marginTop: spacing.sm,
    marginBottom: spacing.lg,
    ...typography.caption,
  },
  methodCard: {
    backgroundColor: colors.surfaceCream,
    borderColor: colors.primary,
    marginBottom: spacing.md,
  },
  cardTitle: {
    ...typography.label,
    color: colors.text,
    fontWeight: '700',
    marginBottom: spacing.sm,
  },
  methodBadge: { alignSelf: 'flex-start', marginBottom: spacing.sm },
  body: { ...typography.body, textAlign: 'center' },
  meta: { ...typography.caption, marginBottom: spacing.sm },
  error: { ...typography.body, color: colors.error, marginBottom: spacing.md },
  note: {
    marginTop: spacing.md,
    ...typography.caption,
    textAlign: 'center',
  },
  actions: { marginTop: spacing.lg },
  successBox: {
    marginTop: spacing.xl,
    alignItems: 'flex-start',
    gap: spacing.sm,
    backgroundColor: colors.successMuted,
    borderColor: colors.success,
  },
  successTitle: {
    ...typography.title,
    color: colors.success,
  },
  inlineButton: {
    marginTop: spacing.md,
    alignSelf: 'stretch',
  },
});
