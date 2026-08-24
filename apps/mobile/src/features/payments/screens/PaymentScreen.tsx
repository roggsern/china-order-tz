import { useCallback, useEffect, useRef, useState } from 'react';
import { AppState, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { createExclusiveLock, runExclusive } from '@/src/core/async/exclusiveLock';
import { shouldRefreshActivePaymentOnResume } from '@/src/shared/hooks/foregroundCommerceRefresh';
import { router, useLocalSearchParams } from 'expo-router';
import { useQueryClient } from '@tanstack/react-query';
import { clearSessionOnAuthFailure, useAuthStore } from '@/src/core/auth';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { fetchOrderDetail } from '@/src/features/orders/api/ordersApi';
import { invalidateAfterPaymentSuccess } from '@/src/features/orders/hooks/useOrders';
import { isOrderPayableFromServer } from '@/src/features/orders/utils/isOrderPayable';
import { buildPostPaymentOrdersHref } from '@/src/features/orders/utils/orderRoutes';
import { Badge } from '@/src/shared/ui/Badge';
import { Card } from '@/src/shared/ui/Card';
import { EmptyState } from '@/src/shared/ui/EmptyState';
import { PrimaryButton } from '@/src/shared/ui/PrimaryButton';
import { ScreenContainer } from '@/src/shared/ui/ScreenContainer';
import { SecondaryButton } from '@/src/shared/ui/SecondaryButton';
import { TrustStrip } from '@/src/shared/ui/TrustStrip';
import { colors, radius, spacing, typography } from '@/src/shared/theme';
import {
  prepareOrderPayment,
  refreshPaymentTransaction,
  startPayment,
} from '../api/paymentsApi';
import { PaymentMethodSelectorCard } from '../components/PaymentMethodSelector';
import { PaymentStatusCard } from '../components/PaymentStatusCard';
import { usePaymentMethods } from '../hooks/usePayments';
import type {
  PaymentOrder,
  PaymentTransaction,
  PreparedPayment,
} from '../models/types';
import { continueNmbCheckout } from '../utils/continueNmbCheckout';
import { ensurePaymentOrder } from '../utils/ensurePaymentOrder';
import { handleNmbPaymentReturn } from '../utils/handlePaymentReturn';
import {
  isPreparedPaymentPaid,
  isSuccessfulPaymentStatus,
  isTerminalPaymentStatus,
} from '../utils/mapPayment';
import {
  buildSelectablePaymentOptions,
  resolveDefaultPaymentCode,
} from '../utils/paymentAvailability';
import {
  getPaymentErrorMessage,
  isPaymentUnauthenticatedError,
} from '../utils/paymentErrorMessage';
import { buildPaymentHref } from '../utils/paymentRoutes';
import {
  applyRefreshedTransaction,
  paymentProviderLabel,
  resolvePaymentStartDecision,
  unsupportedPaymentMethodMessage,
} from '../utils/paymentSession';
import {
  isPaymentInProgressError,
  paymentInProgressCustomerMessage,
  recoveryFromStartError,
  resolvePayNowView,
  type PayNowView,
} from '../utils/payNowRecovery';
import { payOrderWithNmb } from '../utils/payWithNmb';
import {
  clearPaymentAndCheckoutContexts,
} from '../utils/recoveryHandoff';
import {
  isSnippePhoneEntryVisible,
  resolveSnippePhonePrefill,
} from '../utils/snippePhonePrefill';
import { validateSnippePhoneInput } from '../utils/snippePhone';

function firstParam(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

export function PaymentScreen() {
  const authStatus = useAuthStore((s) => s.status);
  const accountPhone = useAuthStore((s) => s.user?.phone ?? null);
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
  const [officePayment, setOfficePayment] = useState<PreparedPayment | null>(null);
  const [view, setView] = useState<PayNowView | null>(null);
  const [selectedCode, setSelectedCode] = useState<string | null>(null);
  const [snippePhoneEdited, setSnippePhoneEdited] = useState(false);
  const [snippePhoneDraft, setSnippePhoneDraft] = useState('');
  const snippePhone = resolveSnippePhonePrefill({
    profilePhone: accountPhone,
    currentValue: snippePhoneDraft,
    editedInSession: snippePhoneEdited,
  });
  const [snippePhoneError, setSnippePhoneError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [statusNote, setStatusNote] = useState<string | null>(null);
  const restoredRef = useRef(false);
  const actionLockRef = useRef(createExclusiveLock());
  const refreshStatusRef = useRef<() => Promise<void>>(async () => undefined);
  const resumeStateRef = useRef<{
    viewKind: string | null;
    transactionId: string | null;
  }>({
    viewKind: null,
    transactionId: null,
  });

  resumeStateRef.current = {
    viewKind: view?.kind ?? null,
    transactionId:
      transaction?.id ??
      (view?.kind === 'recovery' ? view.transaction.id : null),
  };

  const paymentReturnHref = buildPaymentHref({
    orderId: orderIdParam ?? order?.id,
    checkoutSessionId,
    paymentTransactionId: paymentTransactionIdParam ?? transaction?.id,
  });

  const options = methodsQuery.data
    ? buildSelectablePaymentOptions(methodsQuery.data)
    : [];
  const effectiveSelectedCode =
    selectedCode ??
    (methodsQuery.data
      ? resolveDefaultPaymentCode(methodsQuery.data, options)
      : null);

  const applyLoadedTransaction = useCallback(
    (next: PaymentTransaction, fallbackOrderId?: string | null) => {
      setTransaction(next);
      const nextOrderId = next.orderId || next.order?.id || fallbackOrderId;
      if (nextOrderId) {
        setOrder((current) => ({
          id: nextOrderId,
          orderNumber: next.order?.orderNumber ?? current?.orderNumber ?? null,
          status: next.order?.status ?? current?.status ?? null,
          currency: next.currency || current?.currency || 'TZS',
          grandTotal: next.order?.grandTotal ?? current?.grandTotal ?? null,
          checkoutSessionId: checkoutSessionId ?? current?.checkoutSessionId ?? null,
        }));
      }
    },
    [checkoutSessionId],
  );

  useEffect(() => {
    if (authStatus !== 'authenticated' || restoredRef.current) return;
    restoredRef.current = true;

    void (async () => {
      setBusy(true);
      setError(null);
      try {
        if (paymentTransactionIdParam || (orderIdParam && !checkoutSessionId && paymentTransactionIdParam)) {
          const handled = await handleNmbPaymentReturn({
            orderId: orderIdParam,
            paymentTransactionId: paymentTransactionIdParam,
          });
          if (handled.transaction) {
            applyLoadedTransaction(handled.transaction, handled.orderId);
            const restoredView = applyRefreshedTransaction({
              id: handled.transaction.id,
              status: handled.transaction.status,
              provider: handled.transaction.provider,
            });
            setView(restoredView);
            if (restoredView.kind === 'paid') {
              await invalidateAfterPaymentSuccess(queryClient, handled.orderId);
            }
            return;
          }
        }

        if (orderIdParam) {
          const detail = await fetchOrderDetail(orderIdParam);
          setOrder({
            id: detail.id,
            orderNumber: detail.orderNumber,
            status: detail.status,
            currency: detail.currency,
            grandTotal: detail.summary.grandTotal,
            checkoutSessionId: checkoutSessionId ?? null,
          });

          const canPay = isOrderPayableFromServer(detail);
          let nextView = resolvePayNowView({
            canPay,
            orderStatus: detail.status ?? '',
            paymentStatus: detail.payment?.paymentStatus,
            activeTransaction: detail.activePaymentTransaction,
          });

          if (nextView.kind === 'recovery') {
            try {
              const refreshed = await refreshPaymentTransaction(nextView.transaction.id);
              applyLoadedTransaction(refreshed, refreshed.orderId || detail.id);
              nextView = applyRefreshedTransaction({
                id: refreshed.id,
                status: refreshed.status,
                provider: refreshed.provider,
              });
              if (nextView.kind === 'paid') {
                await invalidateAfterPaymentSuccess(queryClient, detail.id);
              } else if (nextView.kind === 'selector') {
                setStatusNote(
                  'The previous payment request is no longer active. Choose a payment method.',
                );
              } else {
                setStatusNote('Your previous payment request is still pending.');
              }
            } catch (refreshError) {
              setTransaction(null);
              setView(nextView);
              setError(getPaymentErrorMessage(refreshError));
              return;
            }
          }

          setView(nextView);
          return;
        }

        setView({ kind: 'selector' });
      } catch (err) {
        if (isPaymentUnauthenticatedError(err)) {
          await clearSessionOnAuthFailure();
          router.push(buildLoginHref(paymentReturnHref));
          return;
        }
        setError(getPaymentErrorMessage(err));
        setView({ kind: 'selector' });
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
    applyLoadedTransaction,
  ]);

  async function redirectToLogin() {
    await clearSessionOnAuthFailure();
    router.push(buildLoginHref(paymentReturnHref));
  }

  async function markPaidFromBackend(orderId?: string | null) {
    setView({ kind: 'paid' });
    await invalidateAfterPaymentSuccess(queryClient, orderId);
    await clearPaymentAndCheckoutContexts();
  }

  function enterRecoveryFromError(err: unknown) {
    const recovered = recoveryFromStartError(err);
    if (recovered) {
      setView({ kind: 'recovery', transaction: recovered });
      setStatusNote(paymentInProgressCustomerMessage());
      setError(null);
      return true;
    }
    if (isPaymentInProgressError(err)) {
      setError(paymentInProgressCustomerMessage());
      return true;
    }
    return false;
  }

  async function startSelectedPaymentUnlocked() {
    const currentView = view ?? { kind: 'selector' as const };
    const decision = resolvePaymentStartDecision({
      view: currentView,
      selectedCode: effectiveSelectedCode,
    });

    if (decision.decision === 'recover') {
      await continueRecoveredPaymentUnlocked();
      return;
    }
    if (decision.decision === 'paid') {
      setView({ kind: 'paid' });
      return;
    }
    if (decision.decision === 'not_payable') {
      setView({ kind: 'not_payable', reason: decision.reason });
      return;
    }
    if (decision.decision === 'unsupported') {
      setError(unsupportedPaymentMethodMessage());
      return;
    }
    if (decision.decision !== 'start') {
      return;
    }

    if (decision.flow === 'snippe') {
      const phoneError = validateSnippePhoneInput(snippePhone);
      if (phoneError) {
        setSnippePhoneError(phoneError);
        return;
      }
    }

    setError(null);
    setSnippePhoneError(null);
    setBusy(true);
    try {
      const nextOrder = await ensurePaymentOrder({
        orderId: orderIdParam ?? order?.id,
        checkoutSessionId,
        existingOrder: order,
      });
      setOrder(nextOrder);

      if (decision.flow === 'nmb') {
        const result = await payOrderWithNmb({
          checkoutSessionId,
          orderId: nextOrder.id,
          provider: 'nmb',
          existingTransaction: null,
        });
        setOrder(result.order);
        setTransaction(result.transaction);
        if (isSuccessfulPaymentStatus(result.transaction.status)) {
          await markPaidFromBackend(result.order.id);
        } else {
          setView({
            kind: 'recovery',
            transaction: {
              id: result.transaction.id,
              status: result.transaction.status,
              provider: result.transaction.provider,
            },
          });
        }
        return;
      }

      if (decision.flow === 'snippe') {
        const started = await startPayment(nextOrder.id, {
          provider: 'snippe',
          phoneNumber: snippePhone.trim(),
        });
        applyLoadedTransaction(started, nextOrder.id);
        if (isSuccessfulPaymentStatus(started.status)) {
          await markPaidFromBackend(nextOrder.id);
          return;
        }
        setView({
          kind: 'recovery',
          transaction: {
            id: started.id,
            status: started.status,
            provider: started.provider,
          },
        });
        setStatusNote('Payment request sent. Approve it on your phone when prompted.');
        return;
      }

      const prepared = await prepareOrderPayment(nextOrder.id, 'cash');
      setOfficePayment(prepared);
      setTransaction(null);
      if (isPreparedPaymentPaid(prepared.status)) {
        await markPaidFromBackend(nextOrder.id);
        return;
      }
      setView({ kind: 'selector' });
      setStatusNote(
        'Pay at Office. Your order stays unpaid until an authorized administrator confirms payment.',
      );
    } catch (err) {
      if (isPaymentUnauthenticatedError(err)) {
        redirectToLogin();
        return;
      }
      if (enterRecoveryFromError(err)) {
        return;
      }
      setError(getPaymentErrorMessage(err));
    } finally {
      setBusy(false);
    }
  }

  async function continueRecoveredPaymentUnlocked() {
    if (view?.kind !== 'recovery') return;
    setBusy(true);
    setError(null);
    try {
      const refreshed = await refreshPaymentTransaction(view.transaction.id);
      applyLoadedTransaction(refreshed, refreshed.orderId || order?.id);
      const next = applyRefreshedTransaction({
        id: refreshed.id,
        status: refreshed.status,
        provider: refreshed.provider,
      });
      setView(next);

      if (next.kind === 'paid') {
        await markPaidFromBackend(refreshed.orderId || order?.id);
        return;
      }
      if (next.kind === 'selector') {
        setTransaction(null);
        setStatusNote('The previous payment request ended. You can choose another payment method.');
        return;
      }

      if (refreshed.provider === 'nmb') {
        const result = await continueNmbCheckout({
          transaction: refreshed,
          checkoutSessionId,
        });
        setOrder(result.order);
        setTransaction(result.transaction);
        if (isSuccessfulPaymentStatus(result.transaction.status)) {
          await markPaidFromBackend(result.order.id);
        }
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

  async function refreshStatusUnlocked() {
    if (!transaction?.id && view?.kind !== 'recovery') return;
    const transactionId = transaction?.id ?? (view?.kind === 'recovery' ? view.transaction.id : null);
    if (!transactionId) return;

    setBusy(true);
    setError(null);
    try {
      const nextTxn = await refreshPaymentTransaction(transactionId);
      applyLoadedTransaction(nextTxn, nextTxn.orderId || orderIdParam);
      const next = applyRefreshedTransaction({
        id: nextTxn.id,
        status: nextTxn.status,
        provider: nextTxn.provider,
      });
      setView(next);
      if (next.kind === 'paid') {
        await markPaidFromBackend(nextTxn.orderId || orderIdParam);
      } else if (next.kind === 'selector') {
        setTransaction(null);
        setStatusNote('The previous payment request ended. You can choose another payment method.');
      } else {
        setStatusNote('Your previous payment request is still pending.');
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

  async function startSelectedPayment() {
    const outcome = await runExclusive(actionLockRef.current, () =>
      startSelectedPaymentUnlocked(),
    );
    if (outcome === 'busy') return;
  }

  async function continueRecoveredPayment() {
    const outcome = await runExclusive(actionLockRef.current, () =>
      continueRecoveredPaymentUnlocked(),
    );
    if (outcome === 'busy') return;
  }

  async function refreshStatus() {
    const outcome = await runExclusive(actionLockRef.current, () =>
      refreshStatusUnlocked(),
    );
    if (outcome === 'busy') return;
  }

  refreshStatusRef.current = refreshStatus;

  useEffect(() => {
    const subscription = AppState.addEventListener('change', (next) => {
      if (next !== 'active') return;
      if (
        !shouldRefreshActivePaymentOnResume({
          viewKind: resumeStateRef.current.viewKind,
          transactionId: resumeStateRef.current.transactionId,
        })
      ) {
        return;
      }
      void refreshStatusRef.current();
    });
    return () => subscription.remove();
  }, []);

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
    !transaction &&
    !officePayment
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

  const paid =
    view?.kind === 'paid' || isSuccessfulPaymentStatus(transaction?.status);
  const notPayable = view?.kind === 'not_payable';
  const recovery = view?.kind === 'recovery';
  const showSelector =
    !paid && !notPayable && !officePayment && view?.kind === 'selector';
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

  const recoveryProvider = recovery
    ? paymentProviderLabel(view.transaction.provider)
    : paymentProviderLabel(transaction?.provider);

  return (
    <ScreenContainer padded={false} style={styles.screen}>
      <ScrollView contentContainerStyle={styles.content}>
        <Text style={styles.eyebrow}>Payment</Text>
        <Text style={styles.heading}>
          {paid
            ? 'Payment confirmed'
            : officePayment
              ? 'Pay at Office'
              : recovery
                ? 'Payment request pending'
                : 'Choose payment method'}
        </Text>
        <Text style={styles.subheading}>
          {paid
            ? 'The server confirmed this payment. View your order for status and tracking.'
            : officePayment
              ? 'Pay at a CHINA ORDER TZ office. Your order stays unpaid until an authorized administrator confirms payment.'
              : recovery
                ? `Continue the existing ${recoveryProvider} request. A new payment will not be started.`
                : 'Choose how you want to pay. Payment is confirmed by the server only.'}
        </Text>

        {error ? <Text style={styles.error}>{error}</Text> : null}
        {statusNote ? <Text style={styles.note}>{statusNote}</Text> : null}

        {notPayable ? (
          <Card elevated={false} style={styles.methodCard}>
            <Text style={styles.cardTitle}>This order cannot be paid now</Text>
            <Text style={styles.meta}>
              {view?.kind === 'not_payable' && view.reason === 'cancelled'
                ? 'The order is cancelled or refunded, so payment is no longer available.'
                : 'Payment is not available for this order.'}
            </Text>
          </Card>
        ) : null}

        {officePayment ? (
          <Card elevated={false} style={styles.methodCard}>
            <Badge label="Pay at Office" tone="brand" style={styles.methodBadge} />
            <Text style={styles.cardTitle}>Payment not completed</Text>
            <Text style={styles.meta}>
              Place your order now and pay at a CHINA ORDER TZ office. Delivery
              starts after payment is confirmed.
            </Text>
            {officePayment.orderNumber || order?.orderNumber ? (
              <Text style={styles.meta}>
                Order {officePayment.orderNumber ?? order?.orderNumber}
              </Text>
            ) : null}
          </Card>
        ) : null}

        {showSelector ? (
          <PaymentMethodSelectorCard
            options={options}
            selectedCode={effectiveSelectedCode}
            onSelect={(code) => {
              setSelectedCode(code);
              setError(null);
            }}
            disabled={busy || methodsQuery.isLoading}
          />
        ) : null}

        {isSnippePhoneEntryVisible({
          viewKind: view?.kind ?? null,
          selectedCode: effectiveSelectedCode,
          hasOfficePayment: Boolean(officePayment),
        }) ? (
          <View style={styles.phoneWrap}>
            <Text style={styles.cardTitle}>Mobile Money number</Text>
            <TextInput
              value={snippePhone}
              onChangeText={(value) => {
                setSnippePhoneEdited(true);
                setSnippePhoneDraft(value);
                if (snippePhoneError) setSnippePhoneError(null);
              }}
              keyboardType="phone-pad"
              placeholder="0712345678"
              placeholderTextColor={colors.textMuted}
              style={styles.phoneInput}
              editable={!busy}
            />
            <Text style={styles.meta}>
              Use the number that should receive the payment prompt.
            </Text>
            {snippePhoneError ? (
              <Text style={styles.error}>{snippePhoneError}</Text>
            ) : null}
          </View>
        ) : null}

        {transaction && !officePayment ? (
          <PaymentStatusCard transaction={transaction} />
        ) : null}

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
        ) : notPayable ? (
          <SecondaryButton
            label="View order"
            onPress={() =>
              router.replace(buildPostPaymentOrdersHref(confirmedOrderId))
            }
            style={styles.inlineButton}
          />
        ) : officePayment ? (
          <PrimaryButton
            label={confirmedOrderId ? 'View order' : 'View my orders'}
            onPress={() => void goToOrdersAfterPayment()}
            style={styles.inlineButton}
          />
        ) : (
          <View style={styles.actions}>
            {recovery ? (
              <>
                {transaction?.provider === 'nmb' || view.transaction.provider === 'nmb' ? (
                  <PrimaryButton
                    label="Continue payment"
                    loading={busy}
                    disabled={busy}
                    onPress={() => void continueRecoveredPayment()}
                    style={styles.inlineButton}
                  />
                ) : null}
                <SecondaryButton
                  label="Check payment status"
                  disabled={busy}
                  onPress={() => void refreshStatus()}
                  style={styles.inlineButton}
                />
              </>
            ) : (
              <PrimaryButton
                label={
                  effectiveSelectedCode === 'cash'
                    ? 'Pay at Office'
                    : effectiveSelectedCode === 'snippe'
                      ? 'Pay with Mobile Money'
                      : effectiveSelectedCode === 'nmb'
                        ? 'Pay with NMB'
                        : 'Continue payment'
                }
                loading={busy}
                disabled={busy || methodsQuery.isLoading || !effectiveSelectedCode}
                onPress={() => void startSelectedPayment()}
                style={styles.inlineButton}
              />
            )}

            {transaction && !recovery ? (
              <SecondaryButton
                label="Check payment status"
                disabled={busy}
                onPress={() => void refreshStatus()}
                style={styles.inlineButton}
              />
            ) : null}

            {terminal && !paid ? (
              <Text style={styles.note}>
                Payment was not completed. You can try again or choose another method.
              </Text>
            ) : null}
          </View>
        )}

        <TrustStrip
          title="What happens next"
          items={[
            {
              id: 'choice',
              title: 'Choose a method',
              description:
                'NMB, Mobile Money, or Pay at Office appear only when the server makes them available.',
            },
            {
              id: 'confirm',
              title: 'Server confirmation',
              description:
                'Closing a provider screen does not mark the order paid. Only a successful server refresh does.',
            },
            {
              id: 'retry',
              title: 'Safe to continue',
              description:
                'If a payment request is already pending, the app restores it instead of starting another one.',
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
  phoneWrap: {
    marginBottom: spacing.md,
  },
  phoneInput: {
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    ...typography.body,
    color: colors.text,
    backgroundColor: colors.background,
    marginBottom: spacing.sm,
  },
});
