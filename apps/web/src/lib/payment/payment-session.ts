const PAYMENT_TRANSACTION_PREFIX = "china-order-tz-payment-txn:";

export function savePaymentTransaction(orderId: string, transactionId: string): void {
  if (typeof window === "undefined") {
    return;
  }

  sessionStorage.setItem(`${PAYMENT_TRANSACTION_PREFIX}${orderId}`, transactionId);
}

export function getPaymentTransaction(orderId: string): string | null {
  if (typeof window === "undefined") {
    return null;
  }

  return sessionStorage.getItem(`${PAYMENT_TRANSACTION_PREFIX}${orderId}`);
}

export function clearPaymentTransaction(orderId: string): void {
  if (typeof window === "undefined") {
    return;
  }

  sessionStorage.removeItem(`${PAYMENT_TRANSACTION_PREFIX}${orderId}`);
}
