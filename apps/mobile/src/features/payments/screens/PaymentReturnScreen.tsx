import { useEffect, useRef, useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';
import { router, useLocalSearchParams } from 'expo-router';
import * as Linking from 'expo-linking';
import { useQueryClient } from '@tanstack/react-query';
import { bootstrapAuth, useAuthStore } from '@/src/core/auth';
import { secureTokenStorage } from '@/src/core/storage';
import { buildLoginHref } from '@/src/features/cart/utils/authReturn';
import { invalidateAfterPaymentSuccess } from '@/src/features/orders/hooks/useOrders';
import { ScreenLoadingState } from '@/src/shared/ui/ScreenLoadingState';
import { colors, spacing, typography } from '@/src/shared/theme';
import { pendingPaymentContextStorage } from '../storage/pendingPaymentContextStorage';
import { handlePaymentReturn } from '../utils/handlePaymentReturn';
import { isSuccessfulPaymentStatus } from '../utils/mapPayment';
import { buildPaymentHref } from '../utils/paymentRoutes';
import { paymentReturnHint } from '../utils/customerPaymentCopy';
import { getPaymentErrorMessage } from '../utils/paymentErrorMessage';

function firstParam(value: string | string[] | undefined): string | undefined {
  return Array.isArray(value) ? value[0] : value;
}

/**
 * Cold/warm deep-link landing for chinaordertz://payment-return
 *
 * Lifecycle:
 * RETURN_RECEIVED → merge hints into SecureStore → AUTH READY?
 *   no  → preserve context, soft-retry bootstrap or login with returnTo
 *   yes → reconcile/refresh once → navigate with server status
 *
 * Does not one-shot before auth is ready. Does not loop reconcile.
 */
export function PaymentReturnScreen() {
  const authStatus = useAuthStore((s) => s.status);
  const bootstrapStatus = useAuthStore((s) => s.bootstrapStatus);
  const queryClient = useQueryClient();
  const params = useLocalSearchParams<{
    resultIndicator?: string | string[];
    result_indicator?: string | string[];
    order_id?: string | string[];
    orderId?: string | string[];
    merchant_reference?: string | string[];
    merchantReference?: string | string[];
    payment_transaction_id?: string | string[];
    paymentTransactionId?: string | string[];
  }>();
  const [message, setMessage] = useState('Confirming payment…');
  const reconcileDoneRef = useRef(false);
  const bootstrapRetriedRef = useRef(false);
  const loginRedirectedRef = useRef(false);

  const statusMessage =
    bootstrapStatus !== 'complete' ? 'Getting things ready…' : message;

  useEffect(() => {
    if (bootstrapStatus !== 'complete') {
      return;
    }
    if (reconcileDoneRef.current) return;

    async function run() {
      try {
        const initialUrl = await Linking.getInitialURL();
        const resultIndicator =
          firstParam(params.resultIndicator) ?? firstParam(params.result_indicator);
        const orderId =
          firstParam(params.order_id) ?? firstParam(params.orderId);
        const merchantReference =
          firstParam(params.merchant_reference) ??
          firstParam(params.merchantReference);
        const paymentTransactionId =
          firstParam(params.payment_transaction_id) ??
          firstParam(params.paymentTransactionId);

        const authUserId = useAuthStore.getState().user?.id ?? null;

        // Always preserve return hints before any auth gate (only set present fields).
        await pendingPaymentContextStorage.merge({
          ...(authUserId ? { userId: authUserId } : {}),
          ...(orderId ? { orderId } : {}),
          ...(paymentTransactionId ? { paymentTransactionId } : {}),
          ...(merchantReference ? { merchantReference } : {}),
          ...(resultIndicator ? { resultIndicator } : {}),
        });

        if (authStatus !== 'authenticated') {
          const token = await secureTokenStorage.readToken();
          if (token && !bootstrapRetriedRef.current) {
            bootstrapRetriedRef.current = true;
            setMessage('Signing you back in…');
            await bootstrapAuth();
            return;
          }

          const persisted = await pendingPaymentContextStorage.readValid();
          const paymentHref = buildPaymentHref({
            orderId: orderId ?? persisted?.orderId,
            paymentTransactionId:
              paymentTransactionId ?? persisted?.paymentTransactionId,
            checkoutSessionId: persisted?.checkoutSessionId,
          });

          if (!loginRedirectedRef.current) {
            loginRedirectedRef.current = true;
            setMessage('Sign in to confirm payment status.');
            router.replace(buildLoginHref(paymentHref) as never);
          }
          return;
        }

        // Authenticated — run shared handler once.
        reconcileDoneRef.current = true;
        setMessage('Confirming payment…');

        const result = await handlePaymentReturn({
          returnUrl: initialUrl,
          resultIndicator,
          orderId,
          merchantReference,
          paymentTransactionId,
        });

        const resolvedOrderId = result.orderId;
        const resolvedTxnId = result.transaction?.id ?? null;
        const paymentHref = buildPaymentHref({
          orderId: resolvedOrderId,
          paymentTransactionId: resolvedTxnId,
        });

        if (
          result.transaction &&
          isSuccessfulPaymentStatus(result.transaction.status)
        ) {
          await invalidateAfterPaymentSuccess(queryClient, resolvedOrderId);
        }

        if (!resolvedOrderId && !resolvedTxnId) {
          setMessage('Unable to confirm payment. Opening payment…');
          router.replace('/(app)/payment');
          return;
        }

        router.replace(paymentHref as never);
      } catch (error) {
        reconcileDoneRef.current = true;
        setMessage(getPaymentErrorMessage(error));
        router.replace('/(app)/payment');
      }
    }

    void run();
  }, [authStatus, bootstrapStatus, params, queryClient]);

  return (
    <View style={styles.wrap}>
      <ScreenLoadingState label={statusMessage} />
      <Text style={styles.hint}>
        {paymentReturnHint()}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    flex: 1,
    backgroundColor: colors.background,
  },
  hint: {
    ...typography.caption,
    textAlign: 'center',
    paddingHorizontal: spacing.xxl,
    paddingBottom: spacing.huge,
    marginTop: -spacing.xl,
  },
});
