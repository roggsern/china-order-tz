import { retryNmbCheckoutSession } from '../api/paymentsApi';
import type { PaymentOrder, PaymentTransaction } from '../models/types';
import { handleNmbPaymentReturn } from './handlePaymentReturn';
import { canOpenCheckoutUrl, isNmbWebsiteHostedCheckout } from './mapPayment';
import { launchNmbCheckoutForTransaction } from './nmbBrowser';
import { handOffCheckoutToPayment } from './recoveryHandoff';

export type ContinueNmbResult = {
  order: PaymentOrder;
  transaction: PaymentTransaction;
  browserType: string;
};

function canLaunch(txn: PaymentTransaction): boolean {
  return canOpenCheckoutUrl(txn.checkoutUrl) || isNmbWebsiteHostedCheckout(txn);
}

/**
 * Continue an existing NMB transaction. Never calls POST /payments/start.
 */
export async function continueNmbCheckout(input: {
  transaction: PaymentTransaction;
  checkoutSessionId?: string | null;
}): Promise<ContinueNmbResult> {
  let transaction = input.transaction;
  const orderId = transaction.orderId || transaction.order?.id || '';

  if (!canLaunch(transaction)) {
    transaction = await retryNmbCheckoutSession(transaction.id);
  }

  if (!canLaunch(transaction)) {
    throw new Error(
      'NMB checkout is not ready yet. Please retry in a moment or contact support if this continues.',
    );
  }

  await handOffCheckoutToPayment({
    orderId,
    paymentTransactionId: transaction.id,
    merchantReference: transaction.merchantReference,
    successIndicator: transaction.successIndicator,
    checkoutSessionId: input.checkoutSessionId ?? null,
  });

  const browser = await launchNmbCheckoutForTransaction(transaction);

  if (browser.url || browser.returnParams.resultIndicator) {
    const handled = await handleNmbPaymentReturn({
      returnUrl: browser.url,
      orderId,
      paymentTransactionId: transaction.id,
      merchantReference: transaction.merchantReference,
      resultIndicator: browser.returnParams.resultIndicator,
    });
    if (handled.transaction) {
      return {
        order: {
          id: orderId,
          orderNumber: handled.transaction.order?.orderNumber ?? null,
          status: handled.transaction.order?.status ?? null,
          currency: handled.transaction.currency,
          grandTotal: handled.transaction.order?.grandTotal ?? null,
          checkoutSessionId: input.checkoutSessionId ?? null,
        },
        transaction: handled.transaction,
        browserType: browser.type,
      };
    }
  }

  const handled = await handleNmbPaymentReturn({
    orderId,
    paymentTransactionId: transaction.id,
    merchantReference: transaction.merchantReference,
  });

  return {
    order: {
      id: orderId,
      orderNumber: transaction.order?.orderNumber ?? null,
      status: transaction.order?.status ?? null,
      currency: transaction.currency,
      grandTotal: transaction.order?.grandTotal ?? null,
      checkoutSessionId: input.checkoutSessionId ?? null,
    },
    transaction: handled.transaction ?? transaction,
    browserType: browser.type,
  };
}
