import {
  createOrderFromCheckoutSession,
  retryNmbCheckoutSession,
  startPayment,
} from '../api/paymentsApi';
import type { PaymentOrder, PaymentTransaction } from '../models/types';
import { handleNmbPaymentReturn } from './handlePaymentReturn';
import { canOpenCheckoutUrl, isNmbWebsiteHostedCheckout } from './mapPayment';
import { launchNmbCheckoutForTransaction } from './nmbBrowser';
import { handOffCheckoutToPayment } from './recoveryHandoff';

export type PayWithNmbResult = {
  order: PaymentOrder;
  transaction: PaymentTransaction;
  browserType: string;
};

/**
 * Orchestrates Contract v1 payment path without trusting browser return as paid.
 * Supports:
 * - redirect checkout_url (allowlisted HTTPS)
 * - Website Hosted Checkout when session id exists without checkout_url
 */
export async function payOrderWithNmb(input: {
  checkoutSessionId?: string | null;
  orderId?: string | null;
  provider?: string | null;
  existingTransaction?: PaymentTransaction | null;
}): Promise<PayWithNmbResult> {
  let order: PaymentOrder;

  if (input.orderId?.trim()) {
    order = {
      id: input.orderId.trim(),
      orderNumber: null,
      status: null,
      currency: 'TZS',
      grandTotal: null,
      checkoutSessionId: input.checkoutSessionId ?? null,
    };
  } else if (input.checkoutSessionId?.trim()) {
    order = await createOrderFromCheckoutSession(input.checkoutSessionId.trim());
  } else {
    throw new Error('Checkout session or order is required to start payment.');
  }

  const canLaunch = (txn: PaymentTransaction) =>
    canOpenCheckoutUrl(txn.checkoutUrl) || isNmbWebsiteHostedCheckout(txn);

  let transaction =
    input.existingTransaction &&
    input.existingTransaction.orderId === order.id &&
    canLaunch(input.existingTransaction)
      ? input.existingTransaction
      : await startPayment(order.id, input.provider ?? 'nmb');

  if (!canLaunch(transaction)) {
    transaction = await retryNmbCheckoutSession(transaction.id);
  }

  if (!canLaunch(transaction)) {
    throw new Error(
      'NMB checkout is not ready yet. Please retry in a moment or contact support if this continues.',
    );
  }

  await handOffCheckoutToPayment({
    orderId: order.id,
    paymentTransactionId: transaction.id,
    merchantReference: transaction.merchantReference,
    successIndicator: transaction.successIndicator,
    checkoutSessionId: input.checkoutSessionId ?? order.checkoutSessionId,
  });

  const browser = await launchNmbCheckoutForTransaction(transaction);

  // Warm return (auth session still alive): reuse shared handler.
  // Cold return uses payment-return route with the same persisted context.
  if (browser.url || browser.returnParams.resultIndicator) {
    const handled = await handleNmbPaymentReturn({
      returnUrl: browser.url,
      orderId: order.id,
      paymentTransactionId: transaction.id,
      merchantReference: transaction.merchantReference,
      resultIndicator: browser.returnParams.resultIndicator,
    });
    if (handled.transaction) {
      return {
        order,
        transaction: handled.transaction,
        browserType: browser.type,
      };
    }
  }

  // Browser cancelled/dismissed — still refresh server status; never invent paid.
  const handled = await handleNmbPaymentReturn({
    orderId: order.id,
    paymentTransactionId: transaction.id,
    merchantReference: transaction.merchantReference,
  });

  return {
    order,
    transaction: handled.transaction ?? transaction,
    browserType: browser.type,
  };
}
