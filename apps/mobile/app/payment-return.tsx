import { PaymentReturnScreen } from '@/src/features/payments';

/**
 * Deep link: chinaordertz://payment-return[?resultIndicator=…]
 * Handles cold-start and warm linking returns from NMB Hosted Checkout.
 */
export default function PaymentReturnRoute() {
  return <PaymentReturnScreen />;
}
